<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 附件（设计文档 §6.8）：上传的文件与它的记录。attachable_type / attachable_id 为空表示尚未绑定（孤儿，由 model:prune 清理）。
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 64);
            $table->string('path', 512);
            $table->string('name');
            $table->string('mime_type', 255)->index();
            $table->string('extension', 32);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('attachable');
            $table->string('collection', 64)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
