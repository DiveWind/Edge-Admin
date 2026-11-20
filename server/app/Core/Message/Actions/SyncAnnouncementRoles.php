<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Enums\AnnouncementTarget;
use App\Core\Message\Models\Announcement;
use App\Core\System\Models\{Role, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 按公告的 target 保存目标角色（设计文档 §6.9），由 CreateAnnouncement、UpdateAnnouncement 在保存公告后调用：
 *
 * - target 为 roles：$names 为角色标识，按标识查找，必须至少一个且全部存在 → 否则 422（roles）；
 * - target 为 all：去掉全部目标角色（$names 忽略）。
 */
class SyncAnnouncementRoles
{
    /**
     * Execute the action.
     *
     * @param  mixed  $names  角色标识（string[]）
     *
     * @throws ValidationException
     */
    public function handle(Announcement $announcement, mixed $names): void
    {
        DB::transaction(function () use ($announcement, $names): void {
            if ($announcement->target !== AnnouncementTarget::Roles) {
                $announcement->roles()->detach();

                return;
            }

            $names = is_array($names) ? array_values(array_filter($names, is_string(...))) : [];

            if ($names === []) {
                throw ValidationException::withMessages(['roles' => [__('validation.required', ['attribute' => __('Roles')])]]);
            }

            $ids = Role::query()->whereIn('name', $names)->where('guard_name', User::GUARD)->pluck('id')->all();

            if (count($ids) !== count(array_unique($names))) {
                throw ValidationException::withMessages(['roles' => [__('validation.exists', ['attribute' => __('Roles')])]]);
            }

            $announcement->roles()->sync($ids);
        });
    }
}
