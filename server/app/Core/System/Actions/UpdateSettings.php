<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\Setting;
use App\Core\System\Services\SettingRegistry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 修改系统参数的值（PUT /system/settings/{setting}、PUT /system/setting-groups/{setting_group}，设计文档 §6.7）：
 *
 * - 只能修改已声明的参数（SettingRegistry），不能新增、删除；未声明的键抛出 InvalidArgumentException（接口在此之前已返回 404 / 422）；
 * - 值已按类型与声明的规则校验（入口的 Form Request），这里按类型转换后保存（`"10"` → 10、`"1"` → true）；
 *   **null 表示恢复默认值**（库中不再保存改过的值）；
 * - 库中还没有该参数（部署后尚未执行 core:sync-settings）时按声明新建；
 * - 多个参数在一个事务中保存；参数缓存在事务提交后清除（SettingObserver）。
 */
class UpdateSettings
{
    public function __construct(private readonly SettingRegistry $registry) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $values  完整的键 => 值（已校验）
     *
     * @throws InvalidArgumentException
     */
    public function handle(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                $declaration = $this->registry->get($key) ?? throw new InvalidArgumentException("系统参数 {$key} 未声明，不能修改。");
                $value = $declaration->type->fromInput($value);

                if ($value !== null && ! $declaration->type->accepts($value)) {
                    throw new InvalidArgumentException("系统参数 {$key} 的值不是 {$declaration->type->value} 类型。");
                }

                $setting = Setting::query()->where('key', $key)->first() ?? new Setting;

                $setting->fillFromDeclaration($declaration);
                $setting->value = $value;
                $setting->save();
            }
        });
    }
}
