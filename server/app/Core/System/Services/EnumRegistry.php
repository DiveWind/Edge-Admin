<?php

namespace App\Core\System\Services;

use App\Core\System\Contracts\{HasColor, HasLabel};
use App\Core\System\Models\DictItem;
use BackedEnum;
use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;

/**
 * 可下发给前端的枚举（设计文档 §5.5、§6.7）：**显式白名单**。单例，只保存启动时登记的“对外名称 => 枚举类”，不保存请求数据（§10.3）。
 *
 * 各模块在自己 Provider 的 boot() 中登记（Core 的 status、permission_type 等由 System、Monitor 登记）：
 *
 * ```php
 * public function boot(EnumRegistry $enums): void
 * {
 *     $enums->register(['content.article_status' => ArticleStatus::class]);
 * }
 * ```
 *
 * 选项接口（/options/enums）只下发登记过的名称，不按类名反射；label 由 HasLabel::label() 按当前语言（Accept-Language）翻译；
 * 枚举另外实现了 HasColor 时，每个选项再输出 color（登记时校验取值，规则同字典项的 color）。
 */
#[Singleton]
class EnumRegistry
{
    /**
     * 对外名称的格式：小写点分、以字母开头（如 `status`、`login_event`、`content.article_status`）。
     */
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/';

    /**
     * 对外名称 => 枚举类（按登记顺序）。
     *
     * @var array<string, class-string<BackedEnum&HasLabel>>
     */
    private array $enums = [];

    /**
     * 登记枚举。名称不合法或重复、类不是实现 HasLabel 的 backed enum、HasColor 的颜色不合法时抛出异常：这是开发期错误。
     *
     * @param  array<string, class-string>  $enums  对外名称 => 枚举类
     *
     * @throws InvalidArgumentException
     */
    public function register(array $enums): void
    {
        $declared = [];

        foreach ($enums as $name => $enum) {
            $name = (string) $name;

            if (preg_match(self::NAME_PATTERN, $name) !== 1) {
                throw new InvalidArgumentException("枚举的对外名称“{$name}”不合法：应为小写点分、以字母开头的标识，如 content.article_status。");
            }

            if (isset($this->enums[$name]) || isset($declared[$name])) {
                throw new InvalidArgumentException("枚举的对外名称“{$name}”重复登记。");
            }

            if (! is_subclass_of($enum, BackedEnum::class) || ! is_subclass_of($enum, HasLabel::class)) {
                throw new InvalidArgumentException("枚举 {$name} 的类 [{$enum}] 必须是实现 ".HasLabel::class.' 的 backed enum。');
            }

            if (is_subclass_of($enum, HasColor::class)) {
                foreach ($enum::cases() as $case) {
                    if (preg_match(DictItem::COLOR_PATTERN, $case->color()) !== 1) {
                        throw new InvalidArgumentException("枚举 {$name} 的 {$case->name} 的颜色“{$case->color()}”不合法：应为 antd 的预设色名（如 success）或 #RGB / #RRGGBB。");
                    }
                }
            }

            $declared[$name] = $enum;
        }

        $this->enums = [...$this->enums, ...$declared];
    }

    /**
     * 名称是否已登记。
     */
    public function has(string $name): bool
    {
        return isset($this->enums[$name]);
    }

    /**
     * 全部已登记的名称（按登记顺序）。
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->enums);
    }

    /**
     * 选项 `[{ value, label, color? }]`（按 cases() 顺序，label 按当前语言；color 只在枚举实现了 HasColor 时输出）。
     *
     * @return list<array{value: int|string, label: string, color?: string}>
     *
     * @throws InvalidArgumentException 名称未登记
     */
    public function options(string $name): array
    {
        $enum = $this->enums[$name] ?? throw new InvalidArgumentException("枚举 {$name} 未登记。");

        return array_map(fn (BackedEnum&HasLabel $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            ...($case instanceof HasColor ? ['color' => $case->color()] : []),
        ], $enum::cases());
    }
}
