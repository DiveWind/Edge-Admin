<?php

namespace App\Support\Html;

use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;
use Symfony\Component\HtmlSanitizer\{HtmlSanitizer, HtmlSanitizerConfig, HtmlSanitizerInterface};

/**
 * 富文本净化的统一入口（设计文档 §9）：按档位（config('core.html.profiles')）构造 symfony/html-sanitizer 的净化器。
 *
 * 所有档位共用的规则：
 * - allowSafeElements()：W3C 的安全元素与属性（去掉 script、style、iframe、form、on* 事件属性等）；
 * - 链接只允许 config('core.html.link_schemes')（默认 http、https、mailto），a 一律加 rel="noopener noreferrer"；
 * - 输入最长 config('core.html.max_input_length') 字节（包的默认 20000 字节，超出部分会被静默截掉；字段的长度上限由 Form Request 校验）。
 *
 * 档位的键：
 * - media：是否允许图片等媒体（img、audio、video、source、track、picture）；允许时地址只能是 config('core.html.media_schemes')（默认只有 https）；
 * - media_hosts：只允许这些主机的媒体（null 表示不限主机）。
 *
 * 单例：净化器构造后只读（配置不可变），按档位缓存在本实例中，不保存请求数据（常驻内存安全，§10.3）。
 * app/ 中只有本命名空间使用 symfony/html-sanitizer（tests/Arch 检查）：模型用 SanitizedHtml 类型转换，其他代码调用 sanitize()。
 */
#[Singleton]
class HtmlSanitizers
{
    /**
     * 不允许媒体的档位中去掉的元素。
     *
     * @var list<string>
     */
    public const array MEDIA_ELEMENTS = ['img', 'audio', 'video', 'source', 'track', 'picture'];

    /**
     * 已构造的净化器：档位名 => 净化器。
     *
     * @var array<string, HtmlSanitizerInterface>
     */
    private array $sanitizers = [];

    /**
     * 按档位净化 HTML；$profile 为 null 时使用默认档位（config('core.html.default_profile')）。
     *
     * @throws InvalidArgumentException 档位不存在
     */
    public function sanitize(string $html, ?string $profile = null): string
    {
        return $this->profile($profile)->sanitize($html);
    }

    /**
     * 档位的净化器。
     *
     * @throws InvalidArgumentException 档位不存在
     */
    public function profile(?string $profile = null): HtmlSanitizerInterface
    {
        $profile ??= config()->string('core.html.default_profile');

        return $this->sanitizers[$profile] ??= new HtmlSanitizer($this->config($profile));
    }

    /**
     * 档位的规则。
     *
     * @throws InvalidArgumentException 档位不存在
     */
    private function config(string $profile): HtmlSanitizerConfig
    {
        $options = config("core.html.profiles.{$profile}");

        if (! is_array($options)) {
            throw new InvalidArgumentException("The HTML sanitizer profile [{$profile}] is not defined in config('core.html.profiles').");
        }

        $config = new HtmlSanitizerConfig()
            ->allowSafeElements()
            ->allowLinkSchemes($this->strings(config('core.html.link_schemes')))
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(config()->integer('core.html.max_input_length'));

        if (($options['media'] ?? false) !== true) {
            return array_reduce(self::MEDIA_ELEMENTS, fn (HtmlSanitizerConfig $config, string $element): HtmlSanitizerConfig => $config->dropElement($element), $config);
        }

        $hosts = $options['media_hosts'] ?? null;

        return $config
            ->allowMediaSchemes($this->strings(config('core.html.media_schemes')))
            ->allowMediaHosts($hosts === null ? null : $this->strings($hosts));
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $values): array
    {
        return is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
    }
}
