<?php

namespace App\Core\Monitor\Services;

use Illuminate\Support\Str;

/**
 * 脱敏与截断（设计文档 §6.6）：操作日志的请求体与路由参数（LogOperation）、失败任务的 payload 与异常（FailedJob）、
 * 定时任务的输出（ScheduledTaskLog 的输出）共用同一份规则。
 *
 * - 键名规则：键名（不区分大小写，支持 * 通配）与 config('core.monitor.masked_keys') 匹配的值整体替换为 MASK；
 * - 数组（maskArray()）：递归处理嵌套对象与数组，键名匹配的值整体替换（值是数组时同样整体替换），其余过长的字符串值截断并加上 TRUNCATED；
 * - 文本（maskText()）：`键=值`、`键: 值`、`键 => 值`、`"键":"值"` 形式且键名匹配的值替换为 MASK（引号保留）。
 *   只认这几种写法，SQL 绑定值等其他形式无法识别；
 * - truncate()：按字符数截断并加上 TRUNCATED。
 *
 * 无状态：每次调用读取配置，可在常驻内存下安全复用。
 */
class DataMasker
{
    /**
     * 脱敏后的值。
     */
    public const string MASK = '******';

    /**
     * 截断的字符串末尾的标记。
     */
    public const string TRUNCATED = '…(truncated)';

    /**
     * 文本中值的写法：双引号、单引号包围的字符串，或到空白与分隔符为止的一段。
     */
    private const string TEXT_VALUE = '"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|[^\s,;&)\]}"\']+';

    /**
     * 递归脱敏，并把超过 $maxValueLength 个字符的字符串值截断。
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function maskArray(array $data, int $maxValueLength): array
    {
        return $this->maskWith($data, $this->patterns(), $maxValueLength);
    }

    /**
     * 文本中键名匹配的值替换为 MASK。只有通配符的规则（`*`）不用于文本。
     */
    public function maskText(string $text): string
    {
        $patterns = array_filter($this->patterns(), fn (string $pattern): bool => trim($pattern, '*') !== '');

        if ($patterns === [] || $text === '') {
            return $text;
        }

        $keys = implode('|', array_map(
            fn (string $pattern): string => implode('[\w-]*', array_map(fn (string $part): string => preg_quote($part, '/'), explode('*', $pattern))),
            $patterns,
        ));

        return (string) preg_replace_callback(
            '/(?<![\w-])(["\']?)(?:'.$keys.')\1\s*(?:=>|[:=])\s*(?<value>'.self::TEXT_VALUE.')/i',
            function (array $match): string {
                $value = $match['value'];
                $quote = in_array($value[0], ['"', "'"], true) ? $value[0] : '';

                return substr($match[0], 0, -strlen($value)).$quote.self::MASK.$quote;
            },
            $text,
        );
    }

    /**
     * 超过 $maxLength 个字符时保留前面部分并加上 TRUNCATED。
     */
    public function truncate(string $value, int $maxLength): string
    {
        return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength).self::TRUNCATED : $value;
    }

    /**
     * 配置的键名规则（小写，去掉空值）。
     *
     * @return list<string>
     */
    private function patterns(): array
    {
        $patterns = array_map(
            fn (mixed $pattern): string => Str::lower(is_scalar($pattern) ? (string) $pattern : ''),
            config()->array('core.monitor.masked_keys'),
        );

        return array_values(array_filter($patterns, fn (string $pattern): bool => $pattern !== ''));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $patterns
     * @return array<array-key, mixed>
     */
    private function maskWith(array $data, array $patterns, int $maxLength): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $patterns !== [] && Str::is($patterns, Str::lower($key))) {
                $data[$key] = self::MASK;
            } elseif (is_array($value)) {
                $data[$key] = $this->maskWith($value, $patterns, $maxLength);
            } elseif (is_string($value)) {
                $data[$key] = $this->truncate($value, $maxLength);
            }
        }

        return $data;
    }
}
