<?php

namespace App\Support\Eloquent\Concerns;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * 模型日期时间统一使用应用时区（config('app.timezone')，设计文档 §10.1）。所有模型都必须使用（tests/Arch 强制）。
 *
 * 写库：先把输入换算到应用时区，再按数据库格式保存。框架默认的 fromDateTime() 按输入值自带的时区直接格式化，
 * `2026-11-16T10:30:00+00:00` 会存成 `2026-11-16 10:30:00`，读出时又按应用时区解释，偏移量被丢弃。
 * - 带偏移的输入（`Z`、`+00:00`、其他时区的日期对象）按偏移换算；
 * - 不带偏移的输入（`2026-11-16 18:30:00`、`2026-11-16`）按应用时区解释，与框架行为一致。
 *
 * 输出：模型序列化（toArray / toJson）时输出应用时区、带偏移的 ISO 8601，如 `2026-11-16T18:30:00+08:00`。
 * API Resource 逐个列出字段时，日期字段同样输出 `toIso8601String()`。
 *
 * @phpstan-require-extends Model
 */
trait UsesAppTimezone
{
    /**
     * 把日期时间输入（字符串、时间戳、日期对象）解析为应用时区的日期对象。
     * 也用于查询条件中的时间范围：`where('created_at', '>=', $model->asAppDateTime($input))`。
     */
    public function asAppDateTime(mixed $value): CarbonInterface
    {
        return $this->asDateTime($value)->setTimezone(config()->string('app.timezone'));
    }

    /**
     * 把日期时间转换为可写入数据库的字符串（应用时区）。
     *
     * @param  mixed  $value
     */
    #[Override]
    public function fromDateTime($value): ?string
    {
        return empty($value) ? null : $this->asAppDateTime($value)->format($this->getDateFormat());
    }

    /**
     * 模型序列化时的日期格式：应用时区、带偏移的 ISO 8601。
     */
    #[Override]
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $this->asAppDateTime($date)->toIso8601String();
    }
}
