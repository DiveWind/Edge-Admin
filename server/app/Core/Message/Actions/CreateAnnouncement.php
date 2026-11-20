<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Enums\AnnouncementStatus;
use App\Core\Message\Models\Announcement;
use App\Core\System\Actions\SyncAttachments;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 新增公告（POST /message/announcements，设计文档 §6.9）：新增的公告是草稿，created_by 为操作者。
 *
 * - content 在写入属性时净化（模型的 SanitizedHtml）；未提交 level 时为列默认值 info；
 * - 目标角色（SyncAnnouncementRoles）：target 为 roles 时必须有已存在的角色（否则 422，roles），为 all 时去掉全部目标角色；
 * - 附件（attachment_ids，已由 OwnedAttachment 校验归属）：绑定到 `attachments` 用途（SyncAttachments，加锁复核，422 挂在 attachment_ids）。
 */
class CreateAnnouncement
{
    public function __construct(
        private readonly SyncAnnouncementRoles $syncRoles,
        private readonly SyncAttachments $syncAttachments,
    ) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段：Announcement 的 `#[Fillable]`、roles（角色标识 string[]）、attachment_ids（附件 id 数组，可省略；未提交表示不变，[] 去掉全部），其余键忽略
     *
     * @throws ValidationException
     */
    public function handle(array $attributes, User $actor): Announcement
    {
        return DB::transaction(function () use ($attributes, $actor): Announcement {
            $announcement = new Announcement;
            $announcement->fill(array_intersect_key($attributes, array_flip($announcement->getFillable())));
            $announcement->status = AnnouncementStatus::Draft;
            $announcement->created_by = $actor->id;
            $announcement->save();

            $this->syncRoles->handle($announcement, $attributes['roles'] ?? []);

            if (array_key_exists('attachment_ids', $attributes)) {
                $this->syncAttachments->handle($announcement, Announcement::ATTACHMENT_COLLECTION, $attributes['attachment_ids'] ?? [], 'attachment_ids');
            }

            return $announcement->refresh();
        });
    }
}
