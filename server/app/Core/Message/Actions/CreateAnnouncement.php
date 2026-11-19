<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Enums\{AnnouncementStatus, AnnouncementTarget};
use App\Core\Message\Models\Announcement;
use App\Core\System\Actions\SyncAttachments;
use App\Core\System\Models\{Role, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 新增公告（POST /message/announcements，设计文档 §6.9）：新增的公告是草稿，created_by 为操作者。
 *
 * - content 在写入属性时净化（模型的 SanitizedHtml）；未提交 level 时为列默认值 info；
 * - 目标角色（syncTargetRoles()）：target 为 roles 时必须有已存在的角色（否则 422，roles），为 all 时去掉全部目标角色；
 * - 附件（attachment_ids，已由 OwnedAttachment 校验归属）：绑定到 `attachments` 用途（SyncAttachments，加锁复核，422 挂在 attachment_ids）。
 */
class CreateAnnouncement
{
    /**
     * 可写入的字段（其余键忽略）。
     *
     * @var list<string>
     */
    public const array ATTRIBUTES = ['title', 'content', 'level', 'target'];

    public function __construct(private readonly SyncAttachments $syncAttachments) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段：ATTRIBUTES、roles（角色标识 string[]）、attachment_ids（附件 id 数组，可省略）
     *
     * @throws ValidationException
     */
    public function handle(array $attributes, User $actor): Announcement
    {
        return DB::transaction(function () use ($attributes, $actor): Announcement {
            $announcement = new Announcement(array_intersect_key($attributes, array_flip(self::ATTRIBUTES)));
            $announcement->status = AnnouncementStatus::Draft;
            $announcement->created_by = $actor->id;
            $announcement->save();

            self::syncTargetRoles($announcement, $attributes['roles'] ?? []);
            self::syncFiles($this->syncAttachments, $announcement, $attributes);

            return $announcement->refresh();
        });
    }

    /**
     * 按 target 保存目标角色：roles 时为提交的角色（按标识查找，必须至少一个已存在的角色），all 时清空。
     *
     * @param  mixed  $names  角色标识（string[]）
     *
     * @throws ValidationException
     */
    public static function syncTargetRoles(Announcement $announcement, mixed $names): void
    {
        if ($announcement->target !== AnnouncementTarget::Roles) {
            $announcement->roles()->detach();

            return;
        }

        $names = is_array($names) ? array_values(array_filter($names, is_string(...))) : [];
        $ids = $names === [] ? [] : Role::query()->whereIn('name', $names)->where('guard_name', User::GUARD)->pluck('id')->all();

        if ($names === []) {
            throw ValidationException::withMessages(['roles' => [__('validation.required', ['attribute' => __('Roles')])]]);
        }

        if (count($ids) !== count(array_unique($names))) {
            throw ValidationException::withMessages(['roles' => [__('validation.exists', ['attribute' => __('Roles')])]]);
        }

        $announcement->roles()->sync($ids);
    }

    /**
     * 提交了 attachment_ids 时把附件绑定到公告（未提交表示不变；[] 去掉全部附件）。
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public static function syncFiles(SyncAttachments $syncAttachments, Announcement $announcement, array $attributes): void
    {
        if (! array_key_exists('attachment_ids', $attributes)) {
            return;
        }

        $ids = is_array($attributes['attachment_ids']) ? array_values(array_filter($attributes['attachment_ids'], is_int(...))) : [];

        $syncAttachments->handle($announcement, Announcement::ATTACHMENT_COLLECTION, $ids, 'attachment_ids');
    }
}
