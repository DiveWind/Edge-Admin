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
 * // 修改记录时保持原值不拦：当前值即使已停用（字典项停用、类型停用或删除）也可以原样提交，改成其他值仍只接受启用项
 * 'source' => ['required', 'string', new InDict('content.article_source')->allowing($article?->source)],
 * ```
 *
 * - 接受字符串或整数，按字符串与字典项的 value 比较（其他类型一律不通过）；
 * - 类型不存在、已停用，或该值的字典项已停用时都不通过（错误为 validation.in）；allowing() 给出的值例外；
 * - 读取 Dictionary 的缓存（同一请求内只访问一次缓存存储）。
 */
class InDict implements ValidationRule
{
    /**
     * @param  string  $type  字典类型的编码（dict_types.code）
     * @param  list<string>  $allowed  不论字典状态都放行的值（allowing()）
     */
    public function __construct(
        private readonly string $type,
        private readonly array $allowed = [],
    ) {}

    /**
     * 另外放行这些值（通常是记录当前保存的值），即使它们已不是启用项；null 忽略（新增记录时没有当前值）。
     * 返回新的规则，原规则不变。
     */
    public function allowing(string|int|null ...$values): self
    {
        $allowed = array_map(strval(...), array_filter($values, fn (string|int|null $value): bool => $value !== null));

        return new self($this->type, array_values(array_unique([...$this->allowed, ...$allowed])));
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('validation.in')->translate();

            return;
        }

        if (! in_array((string) $value, $this->allowed, true) && ! resolve(Dictionary::class)->has($this->type, (string) $value)) {
            $fail('validation.in')->translate();
        }
    }
}
