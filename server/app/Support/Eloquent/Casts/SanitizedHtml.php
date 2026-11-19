<?php

namespace App\Support\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * 富文本字段：写入属性时净化 HTML（设计文档 §9 的“保存时在后端净化”，§13）。
 *
 * `'content' => SanitizedHtml::class`：赋值（fill、属性赋值、工厂）时调用容器中的 HtmlSanitizerInterface（AppServiceProvider 中绑定的默认规则：
 * 安全元素与属性、链接 http / https / mailto、媒体 http / https、a 加 rel="noopener noreferrer"），任何写入路径存进库的都是净化后的内容；
 * 读取时原样返回（库中已是净化后的 HTML）。null 保持 null。
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class SanitizedHtml implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : resolve(HtmlSanitizerInterface::class)->sanitize($value);
    }
}
