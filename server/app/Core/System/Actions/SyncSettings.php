<?php

namespace App\Core\System\Actions;

use App\Core\System\Contracts\SettingRegistry;
use App\Core\System\Data\SettingData;
use App\Core\System\Models\Setting;
use App\Core\System\Services\Settings;
use Illuminate\Support\Facades\DB;

/**
 * 把 SettingRegistry 中声明的系统参数同步到 settings 表（设计文档 §6.7）：由 `core:sync-settings` 调用，
 * DatabaseSeeder 与部署流程（§11.4）都执行它。幂等：再次执行没有变更。
 *
 * 1. 声明中有、库中没有的参数：新增（is_builtin，value 为 null，即使用默认值）；
 * 2. 已有参数修正结构性字段 type、group、name、remark、is_public（都由声明决定，接口不能修改），未标记的收编为内置；
 *    value 不动——除非声明修改了类型、库中的值已不符合新类型：置为 null（恢复默认值）并列在 reset 中；
 * 3. 从声明中移除的参数：取消 is_builtin 与 is_public，保留 value，不删除（代码读不到，也不出现在接口中）。
 *
 * 全部写入在一个事务中；最后清除参数缓存（种子数据关闭模型事件时 SettingObserver 不会执行）。
 */
class SyncSettings
{
    public function __construct(
        private readonly SettingRegistry $registry,
        private readonly Settings $settings,
    ) {}

    /**
     * Execute the action.
     *
     * @return array{created: list<string>, updated: list<string>, adopted: list<string>, reset: list<string>, released: list<string>}
     */
    public function handle(): array
    {
        $result = DB::transaction(function (): array {
            $result = ['created' => [], 'updated' => [], 'adopted' => [], 'reset' => [], 'released' => []];

            /** @var array<string, Setting> $existing */
            $existing = Setting::query()->get()->keyBy('key')->all();

            foreach ($this->registry->all() as $key => $declaration) {
                $setting = $existing[$key] ?? new Setting;
                $isNew = ! $setting->exists;
                $adopted = ! $isNew && ! $setting->is_builtin;

                self::fillStructure($setting, $declaration);

                if (! $isNew && $setting->value !== null && ! $declaration->type->accepts($setting->value)) {
                    $setting->value = null;
                    $result['reset'][] = $key;
                }

                if ($isNew) {
                    $result['created'][] = $key;
                } elseif ($adopted) {
                    $result['adopted'][] = $key;
                } elseif ($setting->isDirty()) {
                    $result['updated'][] = $key;
                }

                $setting->save();
            }

            foreach ($existing as $key => $setting) {
                if ($setting->is_builtin && ! $this->registry->has($key)) {
                    $setting->forceFill(['is_builtin' => false, 'is_public' => false])->save();
                    $result['released'][] = $key;
                }
            }

            return $result;
        });

        $this->settings->flush();

        return $result;
    }

    /**
     * 结构性字段以声明为准：key、group、type、name、remark、is_public，并标记 is_builtin（value 不动）。
     * 修改值时库中还没有该参数（尚未同步）也用它新建（UpdateSettings）。
     */
    public static function fillStructure(Setting $setting, SettingData $declaration): void
    {
        $setting->forceFill([
            'key' => $declaration->key,
            'group' => $declaration->group,
            'type' => $declaration->type,
            'name' => $declaration->title,
            'remark' => $declaration->remark,
            'is_public' => $declaration->public,
            'is_builtin' => true,
        ]);
    }
}
