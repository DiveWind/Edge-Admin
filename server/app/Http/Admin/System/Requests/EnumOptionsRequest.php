<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Services\EnumRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 枚举选项的查询参数（GET /options/enums?names=a,b，设计文档 §6.7）：names 为逗号分隔的对外名称。
 * 显式白名单：名称不在 EnumRegistry 中 → 422（names），不按类名反射。
 */
class EnumOptionsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(EnumRegistry $enums): array
    {
        return [
            'names' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($enums): void {
                $unknown = array_filter($this->split(is_string($value) ? $value : ''), fn (string $name): bool => ! $enums->has($name));

                if ($unknown !== []) {
                    $fail(__('Unknown enum: :names.', ['names' => implode(', ', $unknown)]));
                }
            }],
        ];
    }

    /**
     * 请求的名称（已校验，去掉重复，保持顺序）。
     *
     * @return list<string>
     */
    public function names(): array
    {
        return $this->split($this->string('names')->toString());
    }

    /**
     * @return list<string>
     */
    private function split(string $value): array
    {
        return array_values(array_unique(array_map(trim(...), explode(',', $value))));
    }
}
