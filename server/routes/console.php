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

/*
 * 共同属性用 Schedule 分组声明：
 * - withoutOverlapping()：上一次未结束时跳过本次；
 * - onOneServer()：多台服务器同时运行 schedule:run 时只有一台执行（依赖共享缓存锁，默认的 database 缓存即可）。
 */
Schedule::withoutOverlapping()->onOneServer()->group(function (): void {
    Schedule::command('model:prune')
        ->dailyAt('02:00')
        ->description('清理超过保留天数的登录日志、操作日志与任务日志')
        ->storeOutput();

    Schedule::command('sanctum:prune-expired --hours=24')
        ->dailyAt('03:00')
        ->description('清理过期超过 24 小时的访问令牌')
        ->storeOutput();

    Schedule::command('queue:prune-failed --hours=168')
        ->weeklyOn(1, '03:30')
        ->description('清理 7 天前的失败队列任务')
        ->storeOutput();

    Schedule::command('queue:prune-batches --hours=168 --unfinished=168')
        ->dailyAt('03:15')
        ->description('清理 7 天前的队列批处理记录（公告群发）')
        ->storeOutput();
});
