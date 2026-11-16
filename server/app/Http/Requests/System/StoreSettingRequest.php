<?php

namespace App\Http\Requests\System;

use App\Enums\SettingType;
use App\Models\Setting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettingRequest extends FormRequest
{
    /**
     * key 形如 `site.name`（小写字母、数字、下划线，以 `.` 分段）；
     * type 须排在 value 之前（模型按 type 转换 value）。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'alpha_dash', 'max:64'],
            'key' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9_]+(\.[a-z0-9_]+)*$/', Rule::unique('settings')->ignore($this->setting())],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(SettingType::class)],
            'value' => $this->valueRules(),
            'is_public' => ['sometimes', 'boolean'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function setting(): ?Setting
    {
        $setting = $this->route('setting');

        return $setting instanceof Setting ? $setting : null;
    }

    /**
     * value 按 type 校验：number 为数字，boolean 为布尔，json 为数组 / 对象或合法 JSON 字符串。
     *
     * @return list<mixed>
     */
    protected function valueRules(): array
    {
        return match (SettingType::tryFrom($this->string('type')->toString())) {
            SettingType::Number => ['nullable', 'numeric'],
            SettingType::Boolean => ['nullable', 'boolean'],
            SettingType::Json => ['nullable', $this->jsonRule()],
            default => ['nullable', 'string', 'max:65535'],
        };
    }

    protected function jsonRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_array($value) || (is_string($value) && json_validate($value))) {
                return;
            }

            $fail(__('validation.json', ['attribute' => __('validation.attributes.value')]));
        };
    }
}
