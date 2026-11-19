<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 字典类型（设计文档 §6.7）：code 唯一；is_builtin 为模块声明的类型，由 core:sync-dicts 维护。
     */
    public function up(): void
    {
        Schema::create('dict_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('status', 16)->default('enabled');
            $table->string('remark', 500)->nullable();
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dict_types');
    }
};
