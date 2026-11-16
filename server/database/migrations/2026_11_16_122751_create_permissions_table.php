<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 菜单树：directory（目录）/ menu（菜单）/ action（按钮 / 操作），见 docs/architecture.md §3.1、§4
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type', 20);
            $table->string('path')->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('is_external')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->string('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
