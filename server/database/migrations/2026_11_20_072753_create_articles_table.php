<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 文章（业务模块示范 Content，设计文档 §6.11）：content 是净化后的 HTML（模型的 SanitizedHtml 转换）；category 是字典
     * content.article_category 的 value；status 为 draft / published。created_by 是数据范围的归属列（§8.4）。
     * 软删除（deleted_at）：恢复、彻底删除与“已删除”筛选见 §10.1；封面是附件（attachments 表，彻底删除时一并删除）。
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->mediumText('content');
            $table->string('category', 64)->index();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
