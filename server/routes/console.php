<?php

/*
|--------------------------------------------------------------------------
| 调度
|--------------------------------------------------------------------------
|
| 本文件只放调度定义（Schedule::command(...)）。命令写在各模块中，由模块的 ServiceProvider 注册（设计文档 §4.5）。
| 有名称的任务由 spatie/laravel-schedule-monitor 监控（后台的定时任务页，§6.6）；修改后执行 `php artisan schedule-monitor:sync`（部署流程已包含）。
| 不需要监控的任务加 ->doNotMonitor()，闭包任务需要监控时用 ->monitorName('...') 命名。
|
*/

use App\Core\Message\Models\Notification;
use App\Core\Monitor\Models\ScheduledTaskLog;
use App\Core\System\Models\Attachment;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Support\Facades\Schedule;
use Spatie\Activitylog\Commands\CleanActivitylogCommand;
use Spatie\Health\Commands\{DispatchQueueCheckJobsCommand, RunHealthChecksCommand, ScheduleCheckHeartbeatCommand};

// 日志清理（设计文档 §6.6）：删除早于 activitylog.clean_after_days 天的操作日志、登录日志与模块审计日志
Schedule::command(CleanActivitylogCommand::class, ['--force'])->daily()->withoutOverlapping();

// 定时任务的执行记录（设计文档 §6.6）：删除早于 schedule-monitor.delete_log_items_older_than_days 天的记录（包模型的 MassPrunable）
Schedule::command(PruneCommand::class, ['--model' => [ScheduledTaskLog::class]])->daily()->withoutOverlapping();

// 孤儿附件（设计文档 §6.8）：上传后一直没有绑定、超过 core.attachments.orphan_ttl 小时的附件，连同文件删除（Prunable，逐条删除）
Schedule::command(PruneCommand::class, ['--model' => [Attachment::class]])->daily()->withoutOverlapping();

// 已读的通知（设计文档 §6.9）：已读、创建超过 core.message.notifications.read_ttl 天的通知（MassPrunable）；未读的不清理
Schedule::command(PruneCommand::class, ['--model' => [Notification::class]])->daily()->withoutOverlapping();

// 健康检查（设计文档 §6.6）：每分钟执行检查并保存结果，以及调度、队列的心跳。它们每天产生数千条执行记录，不纳入定时任务监控
Schedule::command(RunHealthChecksCommand::class, ['--no-notification'])->everyMinute()->withoutOverlapping()->doNotMonitor();
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute()->doNotMonitor();
Schedule::command(DispatchQueueCheckJobsCommand::class)->everyMinute()->doNotMonitor();
