<?php

namespace App\Http\Requests\System;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettingRequest extends FormRequest
{
    /**
     * json 类型的 value 允许直接提交对象 / 数组：校验前序列化为 JSON 字符串，之后统一按字符串 + `json` 规则校验。
     * 这样任意结构的嵌套键也不会被全局的未知字段拦截（FormRequest::failOnUnknownFields()）误判。
     */
    protected function prepareForValidation(): void
    {
        if (SettingType::tryFrom($this->string('type')->toString()) === SettingType::Json && is_array($this->input('value'))) {
            $this->merge(['value' => json_encode($this->input('value'), JSON_UNESCAPED_UNICODE)]);
        }
    }

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
     * value 按 type 校验：number 为数字，boolean 为布尔，json 为合法 JSON（对象 / 数组已在 prepareForValidation() 中序列化）。
     *
     * @return list<mixed>
     */
    protected function valueRules(): array
    {
        return match (SettingType::tryFrom($this->string('type')->toString())) {
            SettingType::Number => ['nullable', 'numeric'],
            SettingType::Boolean => ['nullable', 'boolean'],
            SettingType::Json => ['nullable', 'string', 'json'],
            default => ['nullable', 'string', 'max:65535'],
        };
    }
}
