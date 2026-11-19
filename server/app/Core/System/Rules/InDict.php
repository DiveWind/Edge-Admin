<?php

namespace App\Core\System\Rules;

use App\Core\System\Services\Dictionary;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 值必须是指定字典类型下的启用项（设计文档 §6.7）：
 *
 * ```php
 * 'source' => ['required', 'string', new InDict('content.article_source')],
 * ```
 *
 * - 接受字符串或整数，按字符串与字典项的 value 比较（其他类型一律不通过）；
 * - 类型不存在、已停用，或该值的字典项已停用时都不通过（错误为 validation.in）；
 * - 读取 Dictionary 的缓存（同一请求内只访问一次缓存存储）。
 */
class InDict implements ValidationRule
{
    /**
     * @param  string  $type  字典类型的编码（dict_types.code）
     */
    public function __construct(private readonly string $type) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ((! is_string($value) && ! is_int($value)) || ! resolve(Dictionary::class)->has($this->type, (string) $value)) {
            $fail('validation.in')->translate();
        }
    }
}
