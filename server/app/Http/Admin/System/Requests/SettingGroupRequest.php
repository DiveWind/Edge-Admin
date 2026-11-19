<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Data\SettingGroupData;
use App\Core\System\Enums\SettingType;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * 按分组批量修改系统参数（PUT /system/setting-groups/{setting_group}，设计文档 §6.7）：`{ <参数名>: 值, … }`。
 *
 * - 键是该分组的参数名（不带分组前缀），校验错误挂在参数名上（前端表单字段名 = 参数名）；只修改提交的参数，未声明的键 → 422；
 * - 每个值可为 null（恢复默认值），按声明的类型与 rules 校验；
 * - 路由参数 {setting_group} 由 AdminServiceProvider 的 Route::bind 解析为已声明的 SettingGroupData，未声明的分组为 404。
 * json 类型的参数的值中的键不按“未声明字段”拒绝（isKnownField()）。
 */
class SettingGroupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->group()->settings as $name => $setting) {
            $rules[$name] = ['sometimes', ...$setting->validationRules()];
        }

        return $rules;
    }

    /**
     * 要修改的分组（路由绑定的声明）。
     */
    public function group(): SettingGroupData
    {
        $group = $this->route('setting_group');

        return $group instanceof SettingGroupData ? $group : throw new LogicException('路由参数 {setting_group} 未绑定到 SettingGroupData。');
    }

    /**
     * 提交的值，以完整的键（`<分组>.<参数名>`）为键（已校验，尚未按类型转换，由 UpdateSettings 转换）。
     *
     * @return array<string, mixed>
     */
    public function settingValues(): array
    {
        $values = [];

        foreach ($this->group()->settings as $name => $setting) {
            if (array_key_exists($name, $validated = $this->validated())) {
                $values[$setting->key] = $validated[$name];
            }
        }

        return $values;
    }

    /**
     * json 类型的参数的值中的键属于值本身，不是未声明的字段。
     *
     * @param  array<array-key, mixed>  $allowedKeys
     */
    protected function isKnownField(string $inputKey, array $allowedKeys): bool
    {
        $name = explode('.', $inputKey, 2)[0];

        return (str_contains($inputKey, '.') && ($this->group()->settings[$name] ?? null)?->type === SettingType::Json)
            || parent::isKnownField($inputKey, $allowedKeys);
    }
}
