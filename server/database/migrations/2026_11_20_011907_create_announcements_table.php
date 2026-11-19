<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 公告（设计文档 §6.9）：content 是净化后的 HTML（模型的 SanitizedHtml 转换）；status 为 draft / published / revoked，
     * target 为 all / roles（目标角色在 announcement_role）。created_by 是数据范围的归属列（§8.4），published_by 是发布人。
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->mediumText('content');
            $table->string('level', 16)->default('info');
            $table->string('status', 16)->default('draft')->index();
            $table->string('target', 16)->default('all');
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
