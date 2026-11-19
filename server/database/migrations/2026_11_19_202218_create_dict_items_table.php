<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 字典项（设计文档 §6.7）：同一类型内 value 唯一；删除类型时级联删除（Action 先删，外键兜底）；
     * is_builtin 为模块声明的字典项，由 core:sync-dicts 维护。
     */
    public function up(): void
    {
        Schema::create('dict_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dict_type_id')->constrained()->cascadeOnDelete();
            $table->string('value', 64);
            $table->string('label');
            $table->string('color', 32)->nullable();
            $table->integer('sort')->default(0);
            $table->string('status', 16)->default('enabled');
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();

            $table->unique(['dict_type_id', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dict_items');
    }
};
