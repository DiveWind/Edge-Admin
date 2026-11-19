<?php

namespace App\Core\Monitor\Models;

use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Database\Factories\Core\Monitor\Models\ScheduledTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Date;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTask;
use Throwable;

/**
 * 定时任务：spatie/laravel-schedule-monitor 从调度定义（routes/console.php）同步的一个任务（多态别名 `scheduled_task`，
 * 见 MonitorServiceProvider；设计文档 §6.6）。
 *
 * - 继承包的模型，config('schedule-monitor.models.monitored_scheduled_task') 指向本类：`schedule-monitor:sync` 与
 *   `schedule:run` 时的事件监听都通过它写入（上次开始、结束、失败、跳过的时间与执行记录）；接口只读；
 * - name 是包的任务名（命令任务为去掉 `php artisan` 的命令及参数，任务类为类名），立即执行按它在调度事件中查找（ExecuteScheduledTask）；
 * - nextRunAt()、isRunning() 由库中的 cron 表达式、时区与时间计算：HTTP 请求不加载调度定义，以最近一次同步为准。
 *
 * 列的类型转换（last_*_at 为日期）沿用包模型的 $casts。
 *
 * @property int $id
 * @property string $name
 * @property string|null $type
 * @property string $cron_expression
 * @property string|null $timezone
 * @property CarbonInterface|null $last_started_at
 * @property CarbonInterface|null $last_finished_at
 * @property CarbonInterface|null $last_failed_at
 * @property CarbonInterface|null $last_skipped_at
 */
#[Table('monitored_scheduled_tasks')]
class ScheduledTask extends MonitoredScheduledTask
{
    /** @use HasFactory<ScheduledTaskFactory> */
    use HasFactory, UsesAppTimezone;

    /**
     * 调度的时区：未单独设置时为 null（按应用时区执行）。包同步时把未设置的时区存为空字符串。
     */
    public function scheduleTimezone(): ?string
    {
        return in_array($this->timezone, [null, ''], true) ? null : $this->timezone;
    }

    /**
     * 下一次运行的时间（应用时区）：与包的 Task::nextRunAt() 同一算法。cron 表达式无法解析时为 null。
     */
    public function nextRunAt(): ?CarbonInterface
    {
        try {
            $next = new CronExpression($this->cron_expression)
                ->getNextRunDate(now(), 0, false, $this->scheduleTimezone() ?? config()->string('app.timezone'));
        } catch (Throwable) {
            return null;
        }

        return Date::instance($next)->setTimezone(config()->string('app.timezone'));
    }

    /**
     * 是否正在运行：上次开始晚于上次结束与上次失败。进程被强制终止时没有结束记录，会一直为 true，直到下一次运行。
     */
    public function isRunning(): bool
    {
        if (! $this->last_started_at instanceof CarbonInterface) {
            return false;
        }

        return array_all([$this->last_finished_at, $this->last_failed_at], fn (?CarbonInterface $endedAt): bool => ! $endedAt instanceof CarbonInterface || ! $endedAt->greaterThanOrEqualTo($this->last_started_at));
    }
}
