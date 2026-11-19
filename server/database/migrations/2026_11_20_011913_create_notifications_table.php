<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 通知中心（设计文档 §6.9）：官方的数据库通知表（php artisan make:notifications-table）。notifiable_type 存多态别名（`user`，§4.5），
     * type 存通知类的对外类型名（InboxNotification::type()），不存 PHP 类名。
     *
     * 唯一的改动：data 由 text 改为 json——撤回公告时按 data->subject 查找已发出的通知，PostgreSQL 的 JSON 路径查询要求列为 json 类型
     * （MySQL、SQLite 不受影响，DatabaseNotification 的 array 转换照常）。
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
