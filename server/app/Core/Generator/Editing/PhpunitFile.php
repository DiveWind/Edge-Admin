<?php

namespace App\Core\Generator\Editing;

/**
 * 在 phpunit.xml 中登记入口（幂等）：把入口名追加到 `CORE_APPS`，并在最后一个 `CORE_*` 变量之后补上该入口的变量。
 *
 * 预期格式：`<env name="CORE_APPS" value="..."/>` 独占一行。
 */
class PhpunitFile
{
    /**
     * 返回登记后的内容；已全部登记时返回 null。
     *
     * @param  array<string, string>  $env  该入口的环境变量（如 CORE_DEVICE_PREFIX => api/device/v1）
     *
     * @throws UnexpectedFormatException
     */
    public static function registerApp(string $xml, string $key, array $env): ?string
    {
        $pattern = '/^(?<indent>[ \t]*)<env name="CORE_APPS" value="(?<apps>[^"]*)"\s*\/>$/m';

        if (preg_match($pattern, $xml, $match) !== 1) {
            throw new UnexpectedFormatException('找不到 `<env name="CORE_APPS" value="..."/>`', self::snippet($key, $env));
        }

        $apps = array_values(array_filter(array_map(trim(...), explode(',', $match['apps']))));
        $updated = $xml;

        if (! in_array($key, $apps, true)) {
            $line = $match['indent'].'<env name="CORE_APPS" value="'.implode(',', [...$apps, $key]).'"/>';
            $updated = (string) preg_replace($pattern, addcslashes($line, '\\$'), $updated, 1);
        }

        $missing = array_filter($env, fn (string $name): bool => ! str_contains($updated, '<env name="'.$name.'"'), ARRAY_FILTER_USE_KEY);

        if ($missing !== []) {
            preg_match_all('/^[ \t]*<env name="CORE_[A-Z0-9_]+" value="[^"]*"\s*\/>$/m', $updated, $lines, PREG_OFFSET_CAPTURE);

            $last = array_last($lines[0]) ?? throw new UnexpectedFormatException('找不到 CORE_* 变量');
            $added = implode('', array_map(
                fn (string $name, string $value): string => "\n".$match['indent'].'<env name="'.$name.'" value="'.e($value).'"/>',
                array_keys($missing),
                $missing,
            ));

            $updated = substr_replace($updated, $added, $last[1] + strlen($last[0]), 0);
        }

        return $updated === $xml ? null : $updated;
    }

    /**
     * 需要手工添加的内容。
     *
     * @param  array<string, string>  $env
     */
    private static function snippet(string $key, array $env): string
    {
        return "<env name=\"CORE_APPS\" value=\"...,{$key}\"/>\n".implode("\n", array_map(
            fn (string $name, string $value): string => '<env name="'.$name.'" value="'.e($value).'"/>',
            array_keys($env),
            $env,
        ));
    }
}
