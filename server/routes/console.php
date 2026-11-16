<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| 这里定义的 Artisan 命令任务会出现在 "系统监控 / 定时任务" 中，
| 执行日志由 ScheduledTask* 事件监听器写入 scheduled_task_logs。
| 生产环境需配置 cron：* * * * * php artisan schedule:run
|
*/

Schedule::command('model:prune')
    ->dailyAt('02:00')
    ->description('清理超过保留天数的登录日志、操作日志与任务日志')
    ->withoutOverlapping()
    ->storeOutput();

Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('03:00')
    ->description('清理过期超过 24 小时的访问令牌')
    ->withoutOverlapping()
    ->storeOutput();

Schedule::command('queue:prune-failed --hours=168')
    ->weeklyOn(1, '03:30')
    ->description('清理 7 天前的失败队列任务')
    ->withoutOverlapping()
    ->storeOutput();
