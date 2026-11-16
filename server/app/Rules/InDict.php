<?php

namespace App\Rules;

use App\Services\Dictionary;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 值必须是指定数据字典中的启用项：`'source' => ['required', new InDict('article_source')]`。
 */
class InDict implements ValidationRule
{
    public function __construct(protected string $code) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_scalar($value) || ! in_array((string) $value, app(Dictionary::class)->values($this->code), true)) {
            $fail('validation.in')->translate();
        }
    }
}
