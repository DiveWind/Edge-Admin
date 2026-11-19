<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Cache, Schema};

/**
 * spatie/laravel-permission 的表（发布后按本项目整理），设计文档 §8.2、§8.5：
 *
 * - permissions 即菜单树：spatie 原有的 name（权限码）、guard_name 之外，加节点的结构与显示字段；
 *   path 唯一（按钮的 path 为 null，不参与唯一）；parent_id 不级联：有子节点的节点不能删除（§8.6）；
 * - 删除节点或角色时，授权（role_has_permissions、model_has_permissions）由外键级联删除；
 * - roles：spatie 原有列之外加显示名、状态、数据范围、备注、排序；
 * - 不使用 spatie 的 teams 功能，表名与列名使用 config/permission.php 的默认值。
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('permissions');
            $table->string('type', 16);
            $table->string('name');
            $table->string('guard_name');
            $table->string('title');
            $table->string('path')->nullable();
            $table->string('component')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort')->default(0);
            $table->string('status', 16)->default('enabled');
            $table->json('meta')->nullable();
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
            $table->unique('path');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('title');
            $table->string('status', 16)->default('enabled');
            $table->string('data_scope', 64)->default('self');
            $table->string('remark', 500)->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        Cache::forget(config()->string('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
