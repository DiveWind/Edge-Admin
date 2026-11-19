<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 系统参数（设计文档 §6.7）：key 唯一（`<分组>.<参数名>`）；value 为 JSON 编码的值，null 表示管理员没有改过（使用声明的默认值）；
     * type、group、name、remark、is_public 由声明决定，core:sync-settings 维护；is_builtin 为仍在声明中的参数。
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 128)->unique();
            $table->string('group', 64)->index();
            $table->string('type', 16);
            $table->string('name');
            $table->string('remark', 500)->nullable();
            $table->text('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
