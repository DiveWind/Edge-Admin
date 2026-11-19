<?php

use App\Core\System\Enums\Status;
use App\Core\System\Models\{Permission, Role, User};

/*
|--------------------------------------------------------------------------
| 角色管理：/system/roles 的列表、详情、新增、修改、删除（设计文档 §6.5、§8.5、§8.6）
|--------------------------------------------------------------------------
|
| 节点来自 System 的内置声明（core:sync-permissions）：system → system.user / system.role / system.menu 及其按钮。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

/**
 * 角色已授予的权限码（按节点 id 排序）。
 *
 * @return list<string>
 */
function grantedCodes(Role $role): array
{
    return $role->permissions()->orderBy('id')->pluck('name')->all();
}

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/system/roles')->assertUnauthorized();
    });

    it('returns 403 without the system.role code', function (): void {
        $this->actingAs(userWithPermissions('system.user'))
            ->getJson('/api/admin/system/roles')
            ->assertForbidden()
            ->assertExactJson(['code' => 403, 'data' => null, 'message' => 'This action is unauthorized.']);
    });

    it('paginates roles ordered by sort and id', function (): void {
        $actor = userWithPermissions('system.role');
        Role::factory()->create(['name' => 'editor', 'sort' => 2]);
        Role::factory()->create(['name' => 'viewer', 'sort' => 1]);

        $this->actingAs($actor)
            ->getJson('/api/admin/system/roles?page=2&pageSize=2')
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.items.*.name', ['viewer', 'editor']);
    });

    it('lists the role fields without permissions', function (): void {
        $role = Role::factory()->create(['name' => 'editor', 'title' => 'Editor', 'sort' => 2, 'remark' => 'Edits']);
        $role->givePermissionTo('system');

        $this->actingAs(superAdminActor())
            ->getJson('/api/admin/system/roles?filter[name]=edit')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['total' => 1, 'items' => [[
                'id' => $role->id,
                'name' => 'editor',
                'title' => 'Editor',
                'status' => 'enabled',
                'data_scope' => 'self',
                'remark' => 'Edits',
                'sort' => 2,
                'is_super_admin' => false,
                'created_at' => $role->created_at?->toIso8601String(),
                'updated_at' => $role->updated_at?->toIso8601String(),
            ]]]]);
    });

    it('filters by name, title and status', function (string $query, array $names): void {
        Role::factory()->create(['name' => 'editor', 'title' => 'Content editor']);
        Role::factory()->disabled()->create(['name' => 'auditor', 'title' => 'Auditor']);

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/roles?{$query}")
            ->assertOk()
            ->assertJsonPath('data.items.*.name', $names);
    })->with([
        'name contains' => ['filter[name]=dit', ['editor', 'auditor']],
        'title contains' => ['filter[title]=content', ['editor']],
        'status equals' => ['filter[status]=disabled', ['auditor']],
    ]);

    it('sorts by the allowed fields', function (): void {
        $old = Role::factory()->create(['name' => 'old', 'sort' => 1]);
        $new = Role::factory()->create(['name' => 'new', 'sort' => 1]);

        $this->actingAs(superAdminActor())
            ->getJson('/api/admin/system/roles?sort=-id')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $new->id)
            ->assertJsonPath('data.items.1.id', $old->id);
    });

    it('returns 422 for filters and sorts outside the allowlist', function (string $query, string $field, string $message): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/roles?{$query}")
            ->assertUnprocessable()
            ->assertExactJson(['code' => 422, 'data' => null, 'message' => $message, 'errors' => [$field => [$message]]]);
    })->with([
        'filter' => ['filter[remark]=x', 'filter', 'Unsupported filter: remark.'],
        'sort' => ['sort=title', 'sort', 'Unsupported sort: title.'],
    ]);

    it('returns 422 for an invalid status filter', function (): void {
        $this->actingAs(superAdminActor())
            ->getJson('/api/admin/system/roles?filter[status]=active')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.status']);
    });
});

describe('show', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/system/roles/1')->assertUnauthorized();
    });

    it('returns 403 without the system.role code', function (): void {
        $role = Role::factory()->create();

        $this->actingAs(userWithPermissions('system.user'))
            ->getJson("/api/admin/system/roles/{$role->id}")
            ->assertForbidden();
    });

    it('returns the role with its permission codes', function (): void {
        $role = Role::factory()->create();
        $role->givePermissionTo('system', 'system.user', 'system.user.create');

        $this->actingAs(userWithPermissions('system.role'))
            ->getJson("/api/admin/system/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('data.name', $role->name)
            ->assertJsonPath('data.permissions', ['system', 'system.user', 'system.user.create']);
    });

    it('marks the super admin role', function (): void {
        $role = Role::query()->where('name', Role::SUPER_ADMIN)->sole();

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/roles/{$role->id}")
            ->assertOk()
            ->assertJsonPath('data.is_super_admin', true)
            ->assertJsonPath('data.permissions', []);
    });

    it('returns 404 for unknown and malformed ids', function (string $id): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/roles/{$id}")
            ->assertNotFound()
            ->assertJsonPath('code', 404);
    })->with(['missing' => '999999', 'not a number' => 'abc']);
});

describe('store', function (): void {
    it('returns 401 for guests', function (): void {
        $this->postJson('/api/admin/system/roles', ['name' => 'editor', 'title' => 'Editor'])->assertUnauthorized();
    });

    it('returns 403 without the system.role.create code', function (): void {
        $this->actingAs(userWithPermissions('system.role', 'system.role.update'))
            ->postJson('/api/admin/system/roles', ['name' => 'editor', 'title' => 'Editor'])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    });

    it('creates a role with defaults and completes the ancestors of the granted codes', function (): void {
        $response = $this->actingAs(userWithPermissions('system.role.create', 'system.user.create'))
            ->postJson('/api/admin/system/roles', ['name' => 'editor', 'title' => 'Editor', 'permissions' => ['system.user.create']]);

        $role = Role::query()->where('name', 'editor')->sole();

        $response->assertCreated()
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonPath('data.status', 'enabled')
            ->assertJsonPath('data.data_scope', 'self')
            ->assertJsonPath('data.sort', 0)
            ->assertJsonPath('data.permissions', ['system', 'system.user', 'system.user.create']);
        expect(grantedCodes($role))->toBe(['system', 'system.user', 'system.user.create']);
    });

    it('saves every submitted field', function (): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/roles', [
                'name' => 'auditor_1', 'title' => 'Auditor', 'status' => 'disabled', 'data_scope' => 'all', 'remark' => 'Read only', 'sort' => 5,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('roles', [
            'name' => 'auditor_1', 'guard_name' => 'web', 'title' => 'Auditor', 'status' => 'disabled', 'data_scope' => 'all', 'remark' => 'Read only', 'sort' => 5,
        ]);
    });

    it('requires name and title', function (): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/roles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'title']);
    });

    it('rejects invalid fields', function (array $payload, string $field): void {
        Role::factory()->create(['name' => 'editor']);

        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/roles', [...['name' => 'reviewer', 'title' => 'Reviewer'], ...$payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertDatabaseMissing('roles', ['name' => 'reviewer']);
    })->with([
        'name not alpha_dash' => [['name' => 'content editor'], 'name'],
        'name taken' => [['name' => 'editor'], 'name'],
        'super-admin name' => [['name' => Role::SUPER_ADMIN], 'name'],
        'unknown status' => [['status' => 'active'], 'status'],
        'unregistered data scope' => [['data_scope' => 'department'], 'data_scope'],
        'sort not an integer' => [['sort' => 'first'], 'sort'],
        'permissions not a list' => [['permissions' => 'system'], 'permissions'],
        'unregistered permission code' => [['permissions' => ['system.unknown']], 'permissions.0'],
        'duplicate permission codes' => [['permissions' => ['system', 'system']], 'permissions.0'],
        'unknown field' => [['guard_name' => 'api'], 'guard_name'],
    ]);

    it('localizes the field names in validation messages', function (): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/roles', ['title' => 'Editor', 'data_scope' => 'department'], ['Accept-Language' => 'zh-CN'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', '标识 不能为空。')
            ->assertJsonPath('errors.data_scope.0', '所选的 数据范围 无效。');
    });

    it('rejects codes that the operator does not have', function (): void {
        $this->actingAs(userWithPermissions('system.role.create', 'system.user'))
            ->postJson('/api/admin/system/roles', ['name' => 'editor', 'title' => 'Editor', 'permissions' => ['system.user', 'system.menu.delete']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.permissions.0', 'You cannot grant permissions you do not have: system.menu.delete.');

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    });

    it('lets super admins grant any registered code', function (): void {
        $disabled = Permission::query()->where('name', 'system.menu.delete')->sole();
        $disabled->update(['status' => Status::Disabled]);

        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/roles', ['name' => 'editor', 'title' => 'Editor', 'permissions' => ['system.menu.delete']])
            ->assertCreated();

        expect(grantedCodes(Role::query()->where('name', 'editor')->sole()))->toBe(['system', 'system.menu', 'system.menu.delete']);
    });
});

describe('update', function (): void {
    beforeEach(function (): void {
        $this->role = Role::factory()->create(['name' => 'editor', 'title' => 'Editor']);
        $this->role->givePermissionTo('system', 'system.user', 'system.user.create');
    });

    it('returns 401 for guests', function (): void {
        $this->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'editor', 'title' => 'Editor'])->assertUnauthorized();
    });

    it('returns 403 without the system.role.update code', function (): void {
        $this->actingAs(userWithPermissions('system.role', 'system.role.create'))
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'editor', 'title' => 'Changed'])
            ->assertForbidden();

        expect($this->role->fresh()?->title)->toBe('Editor');
    });

    it('updates the fields and keeps the permissions when none are submitted', function (): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'writer', 'title' => 'Writer', 'data_scope' => 'all', 'remark' => null])
            ->assertOk()
            ->assertJsonPath('data.name', 'writer')
            ->assertJsonPath('data.permissions', ['system', 'system.user', 'system.user.create']);

        $this->assertDatabaseHas('roles', ['id' => $this->role->id, 'name' => 'writer', 'title' => 'Writer', 'data_scope' => 'all']);
    });

    it('keeps its own name', function (): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'editor', 'title' => 'Editor'])
            ->assertOk();
    });

    it('rejects the name of another role', function (): void {
        Role::factory()->create(['name' => 'writer']);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'writer', 'title' => 'Editor'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('replaces the permissions and completes their ancestors', function (): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'editor', 'title' => 'Editor', 'permissions' => ['system.role.update']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['system', 'system.role', 'system.role.update']);

        expect(grantedCodes($this->role))->toBe(['system', 'system.role', 'system.role.update']);
    });

    it('rejects adding codes that the operator does not have', function (): void {
        $this->actingAs(userWithPermissions('system.role.update', 'system.user.create'))
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'editor', 'title' => 'Editor', 'permissions' => ['system.user.create', 'system.user.delete']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.permissions.0', 'You cannot grant permissions you do not have: system.user.delete.');

        expect(grantedCodes($this->role))->toBe(['system', 'system.user', 'system.user.create']);
    });

    it('keeps the codes that the operator does not have', function (): void {
        $this->actingAs(userWithPermissions('system.role.update', 'system.user'))
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'editor', 'title' => 'Editor', 'permissions' => ['system.role']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['system', 'system.user', 'system.user.create', 'system.role']);
    });

    it('lets the operator remove the codes they have', function (): void {
        $this->actingAs(userWithPermissions('system.role.update', 'system.user.create'))
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'editor', 'title' => 'Editor', 'permissions' => []])
            ->assertOk()
            ->assertJsonPath('data.permissions', []);
    });

    it('keeps the grants of disabled nodes, which are not in the permission tree', function (): void {
        $this->role->givePermissionTo('system.menu', 'system.menu.delete');
        Permission::query()->where('name', 'system.menu')->sole()->update(['status' => Status::Disabled]);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'editor', 'title' => 'Editor', 'permissions' => ['system.role']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['system', 'system.role', 'system.menu', 'system.menu.delete']);
    });

    it('changes the codes of users with the role right away', function (): void {
        $member = User::factory()->create()->assignRole($this->role);

        $this->actingAs($member->fresh())->getJson('/api/admin/auth/codes')
            ->assertJsonPath('data', ['system', 'system.user', 'system.user.create']);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/roles/{$this->role->id}", ['name' => 'editor', 'title' => 'Editor', 'permissions' => ['system.role']])
            ->assertOk();

        $this->actingAs($member->fresh())->getJson('/api/admin/auth/codes')
            ->assertJsonPath('data', ['system', 'system.role']);
    });

    describe('super admin role', function (): void {
        beforeEach(function (): void {
            $this->superAdmin = Role::query()->where('name', Role::SUPER_ADMIN)->sole();
        });

        it('updates the display fields', function (): void {
            $this->actingAs(userWithPermissions('system.role.update'))
                ->putJson("/api/admin/system/roles/{$this->superAdmin->id}", ['name' => Role::SUPER_ADMIN, 'title' => 'Root', 'remark' => 'Everything'])
                ->assertOk()
                ->assertJsonPath('data.title', 'Root')
                ->assertJsonPath('data.is_super_admin', true);
        });

        it('rejects changes to the identifier, disabling and permissions', function (array $payload, string $field, string $message): void {
            $this->actingAs(superAdminActor())
                ->putJson("/api/admin/system/roles/{$this->superAdmin->id}", [...['name' => Role::SUPER_ADMIN, 'title' => 'Root'], ...$payload])
                ->assertUnprocessable()
                ->assertJsonPath("errors.{$field}.0", $message);

            $this->assertDatabaseHas('roles', ['id' => $this->superAdmin->id, 'name' => Role::SUPER_ADMIN, 'title' => 'Super Admin', 'status' => 'enabled']);
            expect(grantedCodes($this->superAdmin))->toBe([]);
        })->with([
            'identifier' => [['name' => 'root'], 'name', 'The identifier of the super admin role cannot be changed.'],
            'disabled' => [['status' => 'disabled'], 'status', 'The super admin role cannot be disabled.'],
            'permissions' => [['permissions' => ['system']], 'permissions', 'The super admin role has all permissions; they cannot be assigned.'],
            'empty permissions' => [['permissions' => []], 'permissions', 'The super admin role has all permissions; they cannot be assigned.'],
        ]);
    });
});

describe('destroy', function (): void {
    it('returns 401 for guests', function (): void {
        $role = Role::factory()->create();

        $this->deleteJson("/api/admin/system/roles/{$role->id}")->assertUnauthorized();
    });

    it('returns 403 without the system.role.delete code', function (): void {
        $role = Role::factory()->create();

        $this->actingAs(userWithPermissions('system.role', 'system.role.update'))
            ->deleteJson("/api/admin/system/roles/{$role->id}")
            ->assertForbidden();

        $this->assertModelExists($role);
    });

    it('deletes the role and its grants', function (): void {
        $role = Role::factory()->create();
        $role->givePermissionTo('system');

        $this->actingAs(userWithPermissions('system.role.delete'))
            ->deleteJson("/api/admin/system/roles/{$role->id}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        $this->assertModelMissing($role);
        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
    });

    it('returns 403 for the super admin role, even to super admins', function (): void {
        $role = Role::query()->where('name', Role::SUPER_ADMIN)->sole();

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/system/roles/{$role->id}")
            ->assertForbidden()
            ->assertExactJson(['code' => 403, 'data' => null, 'message' => 'The super admin role cannot be deleted.']);

        $this->assertModelExists($role);
    });

    it('returns 422 for a role assigned to users', function (): void {
        $role = Role::factory()->create();
        User::factory()->create()->assignRole($role);

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/system/roles/{$role->id}", [], ['Accept-Language' => 'zh-CN'])
            ->assertUnprocessable()
            ->assertExactJson(['code' => 422, 'data' => null, 'message' => '该角色已分配给用户，不能删除。']);

        $this->assertModelExists($role);
    });
});
