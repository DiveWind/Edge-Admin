<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\UpdateSettings;
use App\Core\System\Contracts\SettingRegistry;
use App\Core\System\Data\{SettingData, SettingGroupData};
use App\Core\System\Models\Setting;
use App\Core\System\Services\Settings;
use App\Http\Admin\System\Requests\SettingRequest;
use App\Http\Admin\System\Resources\{SettingGroupResource, SettingResource};
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 系统参数（设计文档 §6.7）：按分组列出已声明的参数、修改单个参数的值。按分组批量修改见 SettingGroupController。
 *
 * 授权由 SettingPolicy 判定（默认拒绝；接口不能新增、删除参数）；写入调用 System 模块的 UpdateSettings。
 * 路由参数 {setting} 是参数的键，由 AdminServiceProvider 的 Route::bind 解析为已声明的 SettingData（未声明 404）。
 */
class SettingController
{
    /**
     * 全部已声明的参数，按分组（数组，不分页）：分组按 order、注册顺序，参数按声明顺序。
     *
     * @return list<SettingGroupResource>
     */
    #[Authorize('viewAny', Setting::class)]
    public function index(SettingRegistry $registry, Settings $settings): array
    {
        return array_map(
            fn (SettingGroupData $group): SettingGroupResource => new SettingGroupResource($group, $settings->entries($group)),
            $registry->groups(),
        );
    }

    /**
     * 修改单个参数：`{ value }`，null 表示恢复默认值；返回修改后的参数。
     */
    #[Authorize('update', Setting::class)]
    public function update(SettingRequest $request, SettingData $setting, UpdateSettings $action, Settings $settings): SettingResource
    {
        $action->handle([$setting->key => $request->settingValue()]);

        return new SettingResource($settings->entry($setting));
    }
}
