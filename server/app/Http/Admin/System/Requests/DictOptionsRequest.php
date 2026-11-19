<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Models\DictType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 字典选项的查询参数（GET /options/dicts?types=a,b，设计文档 §6.7）：types 为逗号分隔的类型编码，最多 MAX_TYPES 个，格式同 dict_types.code。
 * 不存在或已停用的类型不报错（返回空数组）。
 */
class DictOptionsRequest extends FormRequest
{
    /**
     * 一次最多请求的类型数。
     */
    public const int MAX_TYPES = 50;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'types' => ['required', 'string', self::validateTypes(...)],
        ];
    }

    /**
     * 逗号分隔的每个类型编码都合法，且不超过 MAX_TYPES 个。
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public static function validateTypes(string $attribute, mixed $value, Closure $fail): void
    {
        $types = self::split(is_string($value) ? $value : '');

        if (count($types) > self::MAX_TYPES) {
            $fail('validation.max.array')->translate(['max' => (string) self::MAX_TYPES]);
        } elseif (! array_all($types, DictType::isValidCode(...))) {
            $fail('validation.regex')->translate();
        }
    }

    /**
     * 请求的类型编码（已校验，去掉重复，保持顺序）。
     *
     * @return list<string>
     */
    public function types(): array
    {
        return self::split($this->string('types')->toString());
    }

    /**
     * @return list<string>
     */
    private static function split(string $value): array
    {
        return array_values(array_unique(array_map(trim(...), explode(',', $value))));
    }
}
