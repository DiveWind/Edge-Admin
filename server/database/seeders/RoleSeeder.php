<?php

namespace Database\Seeders;

use App\Enums\DataScope;
use App\Enums\PermissionType;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::factory()->create([
            'name' => '超级管理员',
            'code' => Role::SUPER_ADMIN,
            'data_scope' => DataScope::All,
            'sort' => 0,
            'remark' => '拥有全部权限，不可删除',
        ]);

        $editor = Role::factory()->create([
            'name' => '内容编辑',
            'code' => 'editor',
            'data_scope' => DataScope::DepartmentAndChildren,
            'sort' => 1,
            'remark' => '管理本部门及下级部门的文章',
        ]);
        $editor->permissions()->sync(Permission::query()->whereIn('code', [
            'dashboard', 'dashboard.view',
            'content', 'content.category', 'content.category.view',
            'content.article', 'content.article.view', 'content.article.create', 'content.article.update', 'content.article.delete',
            'message', 'message.announcement', 'message.announcement.view', 'message.notification',
            'system', 'system.attachment', 'system.attachment.view',
        ])->pluck('id'));

        $viewer = Role::factory()->create([
            'name' => '只读访客',
            'code' => 'viewer',
            'data_scope' => DataScope::Self,
            'sort' => 2,
            'remark' => '只读访问，仅可见本人数据',
        ]);
        $viewer->permissions()->sync(Permission::query()
            ->where(fn ($query) => $query->where('code', 'like', '%.view')->orWhereIn('type', [PermissionType::Directory, PermissionType::Menu]))
            ->pluck('id'));

        $marketing = Role::factory()->create([
            'name' => '市场主管',
            'code' => 'marketing-manager',
            'data_scope' => DataScope::Custom,
            'sort' => 3,
            'remark' => '自定义数据权限示例：可见市场部各区域',
        ]);
        $marketing->permissions()->sync(Permission::query()->whereIn('code', [
            'dashboard', 'dashboard.view', 'content', 'content.article', 'content.article.view', 'message', 'message.notification',
        ])->pluck('id'));
        $marketing->departments()->sync(Department::query()->whereIn('name', ['市场部', '华东区', '华南区'])->pluck('id'));
    }
}
