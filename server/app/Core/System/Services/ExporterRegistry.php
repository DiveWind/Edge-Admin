<?php

namespace App\Core\System\Services;

use App\Core\System\Contracts\Exporter;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * 已登记的导出类（Core 的扩展点“异步导出”，设计文档 §5.5、§6.10）：**显式白名单**。
 * 单例，只保存启动时登记的“对外名称 => 导出类”，不保存请求数据（§10.3）。
 *
 * ```php
 * public function boot(ExporterRegistry $exporters): void
 * {
 *     $exporters->register(['content.article' => ArticleExporter::class]);
 * }
 * ```
 *
 * 对外名称存入导出记录（exports.exporter），不存类名：移动、重命名类不用迁移数据。格式与通知的类型名相同：
 * 小写点分、以模块名开头（至少两段），全局唯一。
 */
#[Singleton]
class ExporterRegistry
{
    /**
     * 对外名称的格式。
     */
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/';

    /**
     * 对外名称 => 导出类（按登记顺序）。
     *
     * @var array<string, class-string<Exporter<Model>>>
     */
    private array $exporters = [];

    /**
     * 登记导出类。名称不合法或重复、类没有实现 Exporter 时抛出异常：这是开发期错误。
     *
     * @param  array<string, class-string>  $exporters  对外名称 => 导出类
     *
     * @throws InvalidArgumentException
     */
    public function register(array $exporters): void
    {
        $declared = [];

        foreach ($exporters as $name => $exporter) {
            $name = (string) $name;

            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                throw new InvalidArgumentException("导出的对外名称“{$name}”不合法：应为小写点分、以模块名开头的标识，如 content.article。");
            }

            if (isset($this->exporters[$name]) || isset($declared[$name])) {
                throw new InvalidArgumentException("导出的对外名称“{$name}”重复登记。");
            }

            if (! is_subclass_of($exporter, Exporter::class)) {
                throw new InvalidArgumentException("导出 {$name} 的类 [{$exporter}] 必须实现 ".Exporter::class.'。');
            }

            $declared[$name] = $exporter;
        }

        $this->exporters = [...$this->exporters, ...$declared];
    }

    /**
     * 名称是否已登记。
     */
    public function has(string $name): bool
    {
        return isset($this->exporters[$name]);
    }

    /**
     * 全部已登记的名称（按登记顺序）。
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->exporters);
    }

    /**
     * 名称对应的导出类实例（由容器解析）。
     *
     * @return Exporter<Model>
     *
     * @throws InvalidArgumentException 名称未登记
     */
    public function get(string $name): Exporter
    {
        $exporter = resolve($this->exporters[$name] ?? throw new InvalidArgumentException("导出 {$name} 未登记。"));

        return $exporter instanceof Exporter ? $exporter : throw new InvalidArgumentException("导出 {$name} 的类没有实现 ".Exporter::class.'。');
    }
}
