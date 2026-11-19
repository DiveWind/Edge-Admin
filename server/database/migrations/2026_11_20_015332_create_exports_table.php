<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 异步导出的任务（设计文档 §6.10）：生成的文件是绑定在记录上的附件（用途 `file`）。用户删除前由 System 删除他的导出（连同文件），
     * 外键 cascadeOnDelete 兜底；过期的记录由 model:prune 连同文件删除。
     */
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('exporter', 128);
            $table->string('format', 16);
            $table->string('status', 16)->default('queued');
            $table->json('params');
            $table->string('locale', 16);
            $table->unsignedInteger('row_count')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
