<?php

namespace App\Core\System\Models;

use App\Core\System\Actions\DeleteUnboundAttachment;
use App\Core\System\Contracts\PrivateAttachable;
use App\Core\System\Models\Concerns\HasDataScope;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Database\Factories\Core\System\Models\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Scope};
use Illuminate\Database\Eloquent\{Builder, Model, Prunable};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo, Relation};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Throwable;

/**
 * 附件（多态别名 `attachment`，见 SystemServiceProvider），设计文档 §6.8：
 *
 * - disk、path：存储盘与盘内路径（`attachments/<年>/<月>/<日>/<随机串>.<扩展名>`，不使用客户端的文件名）；
 * - name：客户端提供的原始文件名（只用于显示与下载时的文件名）；
 * - mime_type、extension：服务端按文件内容探测的 MIME 与由它推断的扩展名（不信任客户端的 Content-Type 与扩展名）；
 * - size（字节）、sha256；created_by：上传者（HasDataScope 的归属列，§8.4）；
 * - attachable（可空的多态关联）与 collection（用途名，如 `avatar`）：绑定对象，为空表示尚未绑定（孤儿）；
 *   绑定对象实现 PrivateAttachable（如导出记录）时是“私有附件”：访问权只跟随记录，附件管理不列出、管理员不能访问（§6.10）。
 *
 * 写入只在 System 模块的 Action 中进行（StoreAttachment、SyncAttachments、DeleteAttachment、DeleteUnboundAttachment，设计文档 §5.2）。
 * 记录删除后，文件在事务提交后删除（booted() 的 deleted 事件），事务回滚时文件保留。
 * 未绑定、创建超过 config('core.attachments.orphan_ttl') 小时的附件由 `model:prune` 逐条删除（Prunable，触发删除文件）；
 * 逐条删除时在锁内复核“仍未绑定”（prune() → DeleteUnboundAttachment），读出这一批之后才被绑定的附件跳过。
 *
 * @property int $id
 */
#[Fillable(['disk', 'path', 'name', 'mime_type', 'extension', 'size', 'sha256'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasDataScope, HasFactory, Prunable, UsesAppTimezone;

    /**
     * 可以内联预览的 MIME（图片白名单）：其他类型（含 SVG、HTML、PDF）只能下载（§6.8）。也是头像允许的类型。
     *
     * @var list<string>
     */
    public const array PREVIEWABLE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * 文件的存放目录（盘内）。
     */
    public const string DIRECTORY = 'attachments';

    /**
     * 记录删除后（事务提交后）删除文件；删除失败只 report()，不影响已提交的删除。
     */
    protected static function booted(): void
    {
        static::deleted(function (self $attachment): void {
            $disk = $attachment->disk;
            $path = $attachment->path;

            DB::afterCommit(function () use ($disk, $path): void {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable $e) {
                    report($e);
                }
            });
        });
    }

    /**
     * 绑定对象（未绑定时为 null）。
     *
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 上传者（已删除时为 null）。
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 是否已绑定到某条记录。
     */
    public function isBound(): bool
    {
        return $this->attachable_type !== null;
    }

    /**
     * 是否已绑定在 $record 的 $collection 用途上。
     */
    public function isBoundTo(Model $record, ?string $collection): bool
    {
        return $this->attachable_type === $record->getMorphClass()
            && $this->attachable_id === $record->getKey()
            && $this->collection === $collection;
    }

    /**
     * 是否绑定在实现 PrivateAttachable 的记录上（按多态别名判断，绑定对象已不存在时同样成立）。
     */
    public function isPrivate(): bool
    {
        return $this->attachable_type !== null && in_array($this->attachable_type, self::privateAttachableTypes(), true);
    }

    /**
     * 实现 PrivateAttachable 的模型的多态别名（由多态别名表计算，不缓存）。
     *
     * @return list<string>
     */
    public static function privateAttachableTypes(): array
    {
        return array_keys(array_filter(Relation::morphMap(), fn (string $class): bool => is_subclass_of($class, PrivateAttachable::class)));
    }

    /**
     * 是否可以内联预览（图片白名单，不含 SVG）。
     */
    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_MIME_TYPES, true);
    }

    /**
     * 下载时 Content-Disposition 的 ASCII 回退文件名（`filename=`；UTF-8 原名在 `filename*=` 中）：原始文件名转为 ASCII（Str::ascii）、
     * 去掉 `%` 与路径分隔符；转换后扩展名前没有任何字母或数字（纯中文等转成了空串、空白或标点）时，为 `attachment-<id>.<扩展名>`。
     */
    public function asciiFileName(): string
    {
        $ascii = str_replace(['%', '/', '\\'], '', Str::ascii($this->name));

        if (preg_match('/[A-Za-z0-9]/', pathinfo($ascii, PATHINFO_FILENAME)) === 1) {
            return $ascii;
        }

        return "attachment-{$this->id}".($this->extension === '' ? '' : ".{$this->extension}");
    }

    /**
     * 只保留绑定在 $record 的 $collection 用途上的附件。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function boundTo(Builder $query, Model $record, string $collection): void
    {
        $query->where('attachable_type', $record->getMorphClass())
            ->where('attachable_id', $record->getKey())
            ->where('collection', $collection);
    }

    /**
     * 去掉私有附件（绑定在 PrivateAttachable 记录上的，如导出的文件）：附件管理的列表使用。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withoutPrivate(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('attachable_type')->orWhereNotIn('attachable_type', self::privateAttachableTypes()));
    }

    /**
     * 孤儿清理（model:prune）：未绑定、创建于 orphan_ttl 小时之前的附件。
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('attachable_type')
            ->where('created_at', '<=', now()->subHours(config()->integer('core.attachments.orphan_ttl')));
    }

    /**
     * 清理一条（model:prune 逐条调用）：在锁内复核仍未绑定才删除；读出之后才被绑定的跳过（§6.8）。
     */
    public function prune(): bool
    {
        $this->pruning();

        return resolve(DeleteUnboundAttachment::class)->handle($this, skipIfBound: true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'created_by' => 'integer',
            'attachable_id' => 'integer',
        ];
    }
}
