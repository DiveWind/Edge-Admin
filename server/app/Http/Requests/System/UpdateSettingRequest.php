<?php

namespace App\Http\Requests\System;

use App\Models\Setting;
use Illuminate\Validation\Rule;
use Override;

/**
 * 内置参数（is_system）不可修改 key 与 type。
 */
class UpdateSettingRequest extends StoreSettingRequest
{
    #[Override]
    public function rules(): array
    {
        /** @var Setting $setting */
        $setting = $this->route('setting');
        if (! $setting->is_system) {
            return parent::rules();
        }

        return [
            ...parent::rules(),
            'key' => ['required', 'string', Rule::in([$setting->key])],
            'type' => ['required', Rule::in([$setting->type->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'key.in' => __('validation.custom.setting.system_key'),
            'type.in' => __('validation.custom.setting.system_type'),
        ];
    }
}
