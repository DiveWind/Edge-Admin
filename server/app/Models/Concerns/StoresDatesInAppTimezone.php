<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * 日期时间输入统一换算到应用时区（config('app.timezone')）后再写库，所有模型都应使用（见 tests/Arch）。
 *
 * Laravel 默认的 fromDateTime() 按输入值自带的时区直接格式化：`2026-11-16T10:30:00+00:00` 会被存成
 * `2026-11-16 10:30:00`，读出时又按应用时区解释，偏移被丢弃。这里先换算再格式化：
 * - 带偏移的输入（`+00:00`、`Z`、`-05:00`、其他时区的 Carbon 实例）按偏移换算；
 * - 不带偏移的输入（`2026-11-16 18:30:00`、`2026-11-16`）按应用时区解释（与原行为一致）。
 *
 * 数据库中始终是应用时区的本地时间，与方言无关；输出由 Resource 的 `toIso8601String()` 带上偏移。
 *
 * @mixin Model
 */
trait StoresDatesInAppTimezone
{
    /**
     * 把日期时间输入（字符串 / 时间戳 / DateTimeInterface）解析为应用时区的 Carbon 实例。
     * 也用于列表的时间范围过滤：`whereDate('created_at', '>=', $this->asAppDateTime($value))`。
     */
    public function asAppDateTime(mixed $value): CarbonInterface
    {
        return $this->asDateTime($value)->setTimezone(config()->string('app.timezone'));
    }

    /**
     * Convert a DateTime to a storable string.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asAppDateTime($value)->format($this->getDateFormat());
    }
}
