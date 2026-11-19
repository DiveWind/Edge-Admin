<?php

/*
|--------------------------------------------------------------------------
| 调度
|--------------------------------------------------------------------------
|
| 本文件只放调度定义（Schedule::command(...)）。命令写在各模块中，由模块的 ServiceProvider 注册（设计文档 §4.5）。
|
*/

use Illuminate\Support\Facades\Schedule;
use Spatie\Activitylog\Commands\CleanActivitylogCommand;

// 日志清理（设计文档 §6.6）：删除早于 activitylog.clean_after_days 天的操作日志、登录日志与模块审计日志
Schedule::command(CleanActivitylogCommand::class, ['--force'])->daily()->withoutOverlapping();
