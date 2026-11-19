<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Models\Announcement;
use App\Core\System\Actions\SyncAttachments;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 修改公告（PUT /message/announcements/{announcement}，设计文档 §6.9）：只有草稿能修改。
 *
 * - 已发布、已撤回的由 AnnouncementPolicy 返回 403；两人同时操作（另一人刚发布）时，在事务中锁行复核，已不是草稿 → 422（message）；
 * - 字段规则同 CreateAnnouncement：未提交 level 表示不变；目标角色按 target 整体替换；未提交 attachment_ids 表示附件不变。
 */
class UpdateAnnouncement
{
    public function __construct(private readonly SyncAttachments $syncAttachments) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（同 CreateAnnouncement）
     *
     * @throws ValidationException
     * @throws UnprocessableEntityHttpException
     */
    public function handle(Announcement $announcement, array $attributes): Announcement
    {
        return DB::transaction(function () use ($announcement, $attributes): Announcement {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);

            if (! $locked->isDraft()) {
                throw new UnprocessableEntityHttpException(__('Only draft announcements can be edited.'));
            }

            $locked->fill(array_intersect_key($attributes, array_flip(CreateAnnouncement::ATTRIBUTES)));
            $locked->save();

            CreateAnnouncement::syncTargetRoles($locked, $attributes['roles'] ?? []);
            CreateAnnouncement::syncFiles($this->syncAttachments, $locked, $attributes);

            return $locked->refresh();
        });
    }
}
