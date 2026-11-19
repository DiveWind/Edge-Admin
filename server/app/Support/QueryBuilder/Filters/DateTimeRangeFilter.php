<?php

namespace App\Support\QueryBuilder\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * query-builder 的时间区间筛选（设计文档 §6.3）：`filter[created_at]=起,止`。
 *
 * - 两端都是带时区偏移的 ISO 8601（如 dayjs 的 `format()`：`2026-11-19T08:00:00+08:00`，也接受 `Z` 与毫秒）；
 *   任一端可空：`起,` 只限下限，`,止` 只限上限，两端都空时不筛选；两端都包含（`>=`、`<=`）；
 * - 查询字符串中未编码的 `+` 会被解析为空格，偏移量前的空格按 `+` 处理；
 * - 比较前按模型的 fromDateTime() 换算为数据库中的存储格式（使用 UsesAppTimezone 的模型存为应用时区），
 *   因此任何时区的输入都与库中的值正确比较；
 * - 格式非法（缺少逗号、多于两段、没有时区偏移、日期不存在）或下限晚于上限 → 422，错误挂在 `filter.<字段>` 上，
 *   与列表请求中其他筛选值的校验错误（如 `filter.status`）一致。
 *
 * 用法：`QueryBuilder::for(...)->allowedFilters(DateTimeRangeFilter::allowed('created_at'))`。
 *
 * @template TModel of Model
 *
 * @implements Filter<TModel>
 */
final readonly class DateTimeRangeFilter implements Filter
{
    /**
     * 两端之间的分隔符。
     */
    public const string DELIMITER = ',';

    /**
     * 带时区偏移的 ISO 8601：日期、时间（秒与小数秒可省略）、`Z` 或 `±hh:mm` / `±hhmm`。
     */
    private const string PATTERN = '/^(\d{4})-(\d{2})-(\d{2})T(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d{1,6})?)?(?:Z|[+-](?:0\d|1[0-4]):?[0-5]\d)$/';

    /**
     * @param  string|null  $parameter  筛选参数名（错误挂在 `filter.<参数名>` 上）；为 null 时使用列名
     */
    public function __construct(private ?string $parameter = null) {}

    /**
     * 允许的筛选：`$name` 为参数名，`$column` 为列名（默认同参数名）。分隔符固定为逗号，不受 query-builder 全局配置影响。
     */
    public static function allowed(string $name, ?string $column = null): AllowedFilter
    {
        return AllowedFilter::custom($name, new self($name), $column)->delimiter(self::DELIMITER);
    }

    /**
     * @param  Builder<TModel>  $query
     *
     * @throws ValidationException
     */
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        [$from, $to] = $this->parse($value, $this->parameter ?? $property);

        $model = $query->getModel();
        $column = $query->qualifyColumn($property);

        if ($from !== null) {
            $query->where($column, '>=', $model->fromDateTime($from));
        }

        if ($to !== null) {
            $query->where($column, '<=', $model->fromDateTime($to));
        }
    }

    /**
     * 解析区间的两端（null 表示该端不限）。
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     *
     * @throws ValidationException
     */
    public function parse(mixed $value, string $property): array
    {
        $parts = is_string($value) ? explode(self::DELIMITER, $value) : $value;

        if (! is_array($parts) || count($parts) !== 2 || ! array_is_list($parts)) {
            throw $this->invalid($property, __('The time range must be a start and an end separated by a comma.'));
        }

        [$from, $to] = array_map(fn (mixed $part): ?CarbonImmutable => $this->parseDateTime($part, $property), $parts);

        if ($from !== null && $to !== null && $from->greaterThan($to)) {
            throw $this->invalid($property, __('The start of the time range must not be later than its end.'));
        }

        return [$from, $to];
    }

    /**
     * 一端的时间；空值为 null。
     *
     * @throws ValidationException
     */
    private function parseDateTime(mixed $value, string $property): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw $this->invalidDateTime($property);
        }

        // 查询字符串中未编码的 “+” 被解析为空格：`2026-11-19T08:00:00 08:00`
        $value = (string) preg_replace('/ (\d{2}:?\d{2})$/', '+$1', trim($value));

        if (preg_match(self::PATTERN, $value, $matches) !== 1 || ! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw $this->invalidDateTime($property);
        }

        return CarbonImmutable::parse($value);
    }

    private function invalidDateTime(string $property): ValidationException
    {
        return $this->invalid($property, __('The time range must use ISO 8601 date-times with a time zone offset.'));
    }

    private function invalid(string $property, string $message): ValidationException
    {
        $parameter = config('query-builder.parameters.filter', 'filter');

        return ValidationException::withMessages([(is_string($parameter) ? $parameter : 'filter').'.'.$property => [$message]]);
    }
}
