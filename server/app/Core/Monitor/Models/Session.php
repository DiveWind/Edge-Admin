<?php

namespace App\Core\Monitor\Models;

use App\Core\System\Models\User;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Carbon\CarbonInterface;
use Database\Factories\Core\Monitor\Models\SessionFactory;
use Illuminate\Contracts\Session\Session as SessionStore;
use Illuminate\Database\Eloquent\Attributes\{Hidden, Table};
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NoDiscard;
use Override;

/**
 * 会话：database 会话驱动的会话表（config('session.table')、config('session.connection')）中的一行（多态别名 `session`，
 * 见 MonitorServiceProvider；设计文档 §6.6、§7.3）。在线会话列表与强制下线使用。
 *
 * - 会话表由框架的 DatabaseSessionHandler 维护，本模型只读；强制下线（删除）走 LogoutSession；
 * - 完整的会话 ID 就是登录凭据，不对外输出：接口中的会话 id 是它的前 PUBLIC_ID_LENGTH 位（publicId()，路由绑定也按它查找），
 *   不输出会话内容（payload）；
 * - online()：user_id 非空、last_activity 在 session.lifetime 分钟内；会话驱动不是 database 时会话表不是会话的来源，什么都取不到；
 * - visibleTo()：数据范围委托给所属用户（User::visibleTo()，§8.4）。
 *
 * @property string $id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $last_activity
 */
#[Table(keyType: 'string', incrementing: false, timestamps: false)]
#[Hidden(['payload'])]
class Session extends Model
{
    /** @use HasFactory<SessionFactory> */
    use HasFactory, UsesAppTimezone;

    /**
     * 对外的会话 id 长度（框架的会话 ID 为 40 位字母与数字）。
     */
    public const int PUBLIC_ID_LENGTH = 16;

    #[Override]
    public function getTable(): string
    {
        return config()->string('session.table');
    }

    #[Override]
    public function getConnectionName(): ?string
    {
        $connection = config('session.connection');

        return is_string($connection) ? $connection : null;
    }

    /**
     * 所属用户。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 在线的会话：已登录（user_id 非空）且未过期；会话驱动不是 database 时为空。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function online(Builder $query): void
    {
        if (config('session.driver') !== 'database') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereNotNull($query->qualifyColumn('user_id'))
            ->where($query->qualifyColumn('last_activity'), '>=', now()->subMinutes(config()->integer('session.lifetime'))->getTimestamp());
    }

    /**
     * 只保留所属用户对 $actor 可见的会话（§8.4）。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $actor): void
    {
        $query->whereHas('user', fn (Builder $users) => $users->visibleTo($actor));
    }

    /**
     * 会话是否对 $actor 可见（与 visibleTo() 同一规则）。
     */
    #[NoDiscard]
    public function isVisibleTo(User $actor): bool
    {
        return $this->user?->isVisibleTo($actor) ?? false;
    }

    /**
     * 对外的会话 id：会话 ID 的前 PUBLIC_ID_LENGTH 位。
     */
    public function publicId(): string
    {
        return substr($this->id, 0, self::PUBLIC_ID_LENGTH);
    }

    /**
     * 是否为发起本次请求的会话（请求没有启动会话时为 false，如 token 认证的请求）。
     */
    public function isCurrent(): bool
    {
        $store = resolve(SessionStore::class);

        return $store->isStarted() && hash_equals($store->getId(), $this->id);
    }

    /**
     * 最后活动时间（应用时区）。
     */
    public function lastActivityAt(): CarbonInterface
    {
        return $this->asAppDateTime($this->last_activity);
    }

    #[Override]
    public function getRouteKey(): string
    {
        return $this->publicId();
    }

    /**
     * 路由绑定：按对外 id（会话 ID 的前缀）在在线会话中查找。前缀只含字母与数字（路由参数约束），不会被当作通配符；
     * 数据库排序规则不区分大小写时（MySQL 的默认排序规则、SQLite 的 LIKE）可能匹配多行，按大小写精确比较后再按主键取出这一行：
     * 一次取出多行的模型会被标记为禁止延迟加载（Model::preventLazyLoading()），授权时读取 user 关联就会失败。
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    #[Override]
    public function resolveRouteBinding($value, $field = null): ?self
    {
        if (! is_string($value) || preg_match('/\A[A-Za-z0-9]{'.self::PUBLIC_ID_LENGTH.'}\z/', $value) !== 1) {
            return null;
        }

        $id = static::query()->online()->where('id', 'like', $value.'%')->pluck('id')
            ->first(fn (mixed $id): bool => is_string($id) && str_starts_with($id, $value));

        return $id === null ? null : static::query()->online()->whereKey($id)->first();
    }
}
