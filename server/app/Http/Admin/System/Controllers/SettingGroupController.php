<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\UpdateSettings;
use App\Core\System\Data\SettingGroupData;
use App\Core\System\Models\Setting;
use App\Core\System\Services\Settings;
use App\Http\Admin\System\Requests\SettingGroupRequest;
use App\Http\Admin\System\Resources\SettingGroupResource;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 按分组批量修改系统参数（PUT /system/setting-groups/{setting_group}，设计文档 §6.7）：`{ <参数名>: 值, … }`，只修改提交的参数。
 *
 * 路由参数 {setting_group} 由 AdminServiceProvider 的 Route::bind 解析为已声明的 SettingGroupData（未声明 404）；
 * 授权由 SettingPolicy 判定；写入调用 System 模块的 UpdateSettings（一个事务）。
 */
class SettingGroupController
{
    /**
     * 返回修改后的分组（格式同列表中的一项）。
     */
    #[Authorize('update', Setting::class)]
    public function update(SettingGroupRequest $request, SettingGroupData $settingGroup, UpdateSettings $action, Settings $settings): SettingGroupResource
    {
        $action->handle($request->settingValues());

        return new SettingGroupResource($settingGroup, $settings->entries($settingGroup));
    }
}
