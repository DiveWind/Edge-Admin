<?php

namespace App\Core\Message\Models;

use App\Core\Message\Enums\{AnnouncementStatus, AnnouncementTarget, NotificationLevel};
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Attachment, Role, User};
use App\Core\System\Models\Concerns\{HasAttachments, HasDataScope};
use App\Support\Eloquent\Casts\SanitizedHtml;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Carbon\CarbonInterface;
use Database\Factories\Core\Message\Models\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Scope};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, MorphMany};
use Illuminate\Support\Str;

/**
 * 公告（多态别名 `announcement`，见 MessageServiceProvider；设计文档 §6.9）。
 *
 * - content：HTML，写入属性时净化（SanitizedHtml，symfony/html-sanitizer，§9），存的、输出的都是净化后的内容；
 * - level：NotificationLevel（发出的通知同级别）；status：AnnouncementStatus（草稿 → 已发布 → 已撤回，撤回是终态）；
 * - target：AnnouncementTarget（全部用户 / 指定角色，目标角色在 announcement_role，roles()）；
 * - published_at、published_by（发布人）、revoked_at；created_by：创建人（HasDataScope 的归属列，§8.4）；
 * - HasAttachments：附件在 `attachments` 用途上（ATTACHMENT_COLLECTION），删除公告时一并删除。
 *
 * 接收人（recipients()）：启用的用户；目标为 roles 时，另须拥有任一启用的目标角色。
 * “发给了谁”（receivedBy() / isReceivedBy()）：已发布，且目标包含该用户（按当前的角色计算）。
 *
 * 写入只在 Message 模块的 Action 中进行（设计文档 §5.2）。
 *
 * @property int $id
 * @property string $content
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $revoked_at
 */
#[Fillable(['title', 'content', 'level', 'target'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasAttachments, HasDataScope, HasFactory, UsesAppTimezone;

    /**
     * 附件的用途名。
     */
    public const string ATTACHMENT_COLLECTION = 'attachments';

    /**
     * 正文的最大长度（字符，按净化前计）。
     */
    public const int CONTENT_MAX_LENGTH = 65535;

    /**
     * 通知中的正文摘要长度（字符）。
     */
    public const int EXCERPT_LENGTH = 100;

    /**
     * 目标角色（target 为 roles 时）。
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'announcement_role');
    }

    /**
     * 发布人（未发布或已删除时为 null）。
     *
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * 创建人。
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 公告的附件。
     *
     * @return MorphMany<Attachment, $this>
     */
    public function files(): MorphMany
    {
        return $this->attachmentsIn(self::ATTACHMENT_COLLECTION)->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === AnnouncementStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === AnnouncementStatus::Published;
    }

    /**
     * 接收人：启用的用户；目标为 roles 时，另须拥有任一启用的目标角色（停用的角色不生效，§8.4）。
     *
     * @return Builder<User>
     */
    public function recipients(): Builder
    {
        $query = User::query()->where('status', Status::Enabled);

        if ($this->target === AnnouncementTarget::Roles) {
            $query->whereHas('roles', fn (Builder $roles) => $roles
                ->where('roles.status', Status::Enabled)
                ->whereIn('roles.id', $this->roles()->select('roles.id')));
        }

        return $query;
    }

    /**
     * 是否发给了 $user：已发布，且目标为全部用户，或 $user 当前拥有任一启用的目标角色（与 receivedBy() 同一规则）。
     */
    public function isReceivedBy(User $user): bool
    {
        if (! $this->isPublished()) {
            return false;
        }

        if ($this->target === AnnouncementTarget::All) {
            return true;
        }

        return $this->roles()->whereKey($user->enabledRoles()->modelKeys())->exists();
    }

    /**
     * 正文的纯文本摘要（通知的 content）：去掉标签、合并空白，最多 EXCERPT_LENGTH 个字符。
     */
    public function excerpt(): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $this->content)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(Str::squish($text), self::EXCERPT_LENGTH);
    }

    /**
     * 只保留发给 $user 的公告：已发布，且目标为全部用户，或 $user 当前拥有任一启用的目标角色。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function receivedBy(Builder $query, User $user): void
    {
        $query->where('status', AnnouncementStatus::Published)
            ->where(fn (Builder $query) => $query
                ->where('target', AnnouncementTarget::All)
                ->orWhere(fn (Builder $query) => $query
                    ->where('target', AnnouncementTarget::Roles)
                    ->whereHas('roles', fn (Builder $roles) => $roles->whereKey($user->enabledRoles()->modelKeys()))));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => SanitizedHtml::class,
            'level' => NotificationLevel::class,
            'status' => AnnouncementStatus::class,
            'target' => AnnouncementTarget::class,
            'published_at' => 'datetime',
            'published_by' => 'integer',
            'revoked_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }
}
