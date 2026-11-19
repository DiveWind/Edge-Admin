<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-schedule-monitor 的表（发布自包的迁移，设计文档 §6.6）：
 *
 * - monitored_scheduled_tasks：schedule-monitor:sync 从调度定义同步的任务，以及上次开始、结束、失败、跳过的时间；
 * - monitored_scheduled_task_log_items：每次执行的记录（starting / finished / failed / skipped），随任务级联删除，
 *   过期记录由调度中的 model:prune 清理。
 *
 * Oh Dear 相关的列（ping_url、registered_on_oh_dear_at、last_pinged_at）保留，包的同步与写入会用到。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_scheduled_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('cron_expression');
            $table->string('timezone')->nullable();
            $table->string('ping_url')->nullable();
            $table->dateTime('last_started_at')->nullable();
            $table->dateTime('last_finished_at')->nullable();
            $table->dateTime('last_failed_at')->nullable();
            $table->dateTime('last_skipped_at')->nullable();
            $table->dateTime('registered_on_oh_dear_at')->nullable();
            $table->dateTime('last_pinged_at')->nullable();
            $table->integer('grace_time_in_minutes');
            $table->timestamps();
        });

        Schema::create('monitored_scheduled_task_log_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('monitored_scheduled_task_id')
                ->constrained('monitored_scheduled_tasks', indexName: 'fk_scheduled_task_id')
                ->cascadeOnDelete();
            $table->string('type');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }
};
