<?php

namespace App\Support\Eloquent\Casts;

use App\Support\Html\HtmlSanitizers;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * 富文本字段：写入属性时按档位净化 HTML（设计文档 §9 的“保存时在后端净化”，§13）。
 *
 * - `'content' => SanitizedHtml::class`：默认档位（config('core.html.default_profile')，默认 `rich`：含图片）；
 * - `'remark' => SanitizedHtml::class.':basic'`：指定档位（`basic`：不允许图片等媒体），档位见 config('core.html.profiles')。
 *
 * 赋值（fill、属性赋值、工厂）时调用 HtmlSanitizers，任何写入路径存进库的都是净化后的内容；读取时原样返回（库中已是净化后的 HTML）。
 * null 保持 null。档位不存在时抛出 InvalidArgumentException（开发期的错误）。
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class SanitizedHtml implements CastsAttributes
{
    /**
     * @param  string|null  $profile  净化档位；null 为默认档位
     */
    public function __construct(private readonly ?string $profile = null) {}

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
        return $value === null ? null : resolve(HtmlSanitizers::class)->sanitize($value, $this->profile);
    }
}
