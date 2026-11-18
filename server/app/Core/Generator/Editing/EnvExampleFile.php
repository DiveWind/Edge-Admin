<?php

namespace App\Core\Generator\Editing;

/**
 * 在 .env.example 中登记入口（幂等）：把入口名追加到 `CORE_APPS=`（它明确列出了本地加载的入口，不追加则新入口在本地不加载），
 * 并在最后一个 `CORE_*` 变量之后以注释的形式补上该入口的变量。
 *
 * 预期格式：`CORE_APPS=...` 独占一行。
 */
class EnvExampleFile
{
    /**
     * 返回登记后的内容；已全部登记时返回 null。
     *
     * @param  array<string, string>  $env  该入口的环境变量及默认值（以注释写入）
     *
     * @throws UnexpectedFormatException
     */
    public static function registerApp(string $content, string $key, array $env): ?string
    {
        $pattern = '/^CORE_APPS=(?<apps>.*)$/m';

        if (preg_match($pattern, $content, $match) !== 1) {
            throw new UnexpectedFormatException('找不到 `CORE_APPS=` 一行', "CORE_APPS=...,{$key}\n".self::lines($env));
        }

        $apps = array_values(array_filter(array_map(trim(...), explode(',', $match['apps']))));
        $updated = $content;

        if (! in_array($key, $apps, true)) {
            $updated = (string) preg_replace($pattern, addcslashes('CORE_APPS='.implode(',', [...$apps, $key]), '\\$'), $updated, 1);
        }

        $missing = array_filter($env, fn (string $name): bool => preg_match('/^#?\s*'.preg_quote($name, '/').'=/m', $updated) !== 1, ARRAY_FILTER_USE_KEY);

        if ($missing !== []) {
            preg_match_all('/^#?\s*CORE_[A-Z0-9_]+=.*$/m', $updated, $lines, PREG_OFFSET_CAPTURE);

            $last = array_last($lines[0]) ?? throw new UnexpectedFormatException('找不到 CORE_* 变量');

            $updated = substr_replace($updated, "\n".self::lines($missing), $last[1] + strlen($last[0]), 0);
        }

        return $updated === $content ? null : $updated;
    }

    /**
     * @param  array<string, string>  $env
     */
    private static function lines(array $env): string
    {
        return implode("\n", array_map(fn (string $name, string $value): string => "# {$name}={$value}", array_keys($env), $env));
    }
}
