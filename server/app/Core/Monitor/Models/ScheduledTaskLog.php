<?php

namespace App\Core\Monitor\Models;

use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Carbon\CarbonInterface;
use Database\Factories\Core\Monitor\Models\ScheduledTaskLogFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\ScheduleMonitor\Models\MonitoredScheduledTaskLogItem;

/**
 * 定时任务的一条执行记录（多态别名 `scheduled_task_log`，见 MonitorServiceProvider；设计文档 §6.6）。
 *
 * - 继承包的模型，config('schedule-monitor.models.monitored_scheduled_log_item') 指向本类，由包在任务开始、结束、失败、跳过时写入；接口只读；
 * - type：starting / finished / failed / skipped（包的 TYPE_* 常量）；meta：runtime（秒）、exit_code、memory（字节）、
 *   failure_message、output（只有任务调用 storeOutputInDb() 时才有），按 type 只有其中一部分；
 * - 过期记录由调度中的 `model:prune --model=ScheduledTaskLog` 删除（包模型的 MassPrunable，保留天数见 schedule-monitor.delete_log_items_older_than_days）。
 *
 * meta 的类型转换（array）沿用包模型的 $casts。
 *
 * @property int $id
 * @property int $monitored_scheduled_task_id
 * @property string $type
 * @property array<string, mixed>|null $meta
 * @property CarbonInterface|null $created_at
 */
#[Table('monitored_scheduled_task_log_items')]
class ScheduledTaskLog extends MonitoredScheduledTaskLogItem
{
    /** @use HasFactory<ScheduledTaskLogFactory> */
    use HasFactory, UsesAppTimezone;

    /**
     * meta 中的一个值（不存在时为 null）。
     */
    public function metaValue(string $key): mixed
    {
        return $this->meta[$key] ?? null;
    }
}
