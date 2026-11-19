<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Data\SettingData;
use App\Core\System\Enums\SettingType;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * 修改单个系统参数（PUT /system/settings/{setting}，设计文档 §6.7）：`{ value }`。
 *
 * - value 必须提交，可为 null（恢复默认值；空字符串经框架的 ConvertEmptyStringsToNull 同样视为 null）；
 * - 按声明的类型校验（SettingType::rules()），再追加声明的 rules；
 * - 路由参数 {setting} 由 AdminServiceProvider 的 Route::bind 解析为已声明的 SettingData，未声明的键为 404。
 * json 类型的值是数组或对象，其中的键不按“未声明字段”拒绝（isKnownField()）。
 */
class SettingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return ['value' => ['present', ...$this->setting()->validationRules()]];
    }

    /**
     * 字段名（错误提示中使用）。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['value' => __('Value')];
    }

    /**
     * 要修改的参数（路由绑定的声明）。
     */
    public function setting(): SettingData
    {
        $setting = $this->route('setting');

        return $setting instanceof SettingData ? $setting : throw new LogicException('路由参数 {setting} 未绑定到 SettingData。');
    }

    /**
     * 提交的值（已校验，尚未按类型转换，由 UpdateSettings 转换）。
     */
    public function settingValue(): mixed
    {
        return $this->validated('value');
    }

    /**
     * json 类型的 value 中的键属于值本身，不是未声明的字段。
     *
     * @param  array<array-key, mixed>  $allowedKeys
     */
    protected function isKnownField(string $inputKey, array $allowedKeys): bool
    {
        return ($this->setting()->type === SettingType::Json && str_starts_with($inputKey, 'value.'))
            || parent::isKnownField($inputKey, $allowedKeys);
    }
}
