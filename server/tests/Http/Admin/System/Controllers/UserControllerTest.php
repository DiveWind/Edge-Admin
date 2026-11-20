<?php

use App\Core\System\Contracts\DataScopeProvider;
use App\Core\System\Models\{Role, User};
use App\Core\System\Services\DataScopeRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Hash};

/*
|--------------------------------------------------------------------------
| 用户管理：/system/users 的列表、详情、新增、修改、删除（设计文档 §6.5、§8.4、§8.5、§8.6）
|--------------------------------------------------------------------------
|
| 节点来自 System 的内置声明（core:sync-permissions）。userWithPermissions() 的角色选用 `self`（本人与自己创建的用户），
| userWithDataScope('all', ...) 不限制数据范围。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

/**
 * 用户已分配的角色标识（按标识排序）。
 *
 * @return list<string>
 */
function assignedRoles(User $user): array
{
    return $user->roles()->orderBy('name')->pluck('name')->all();
}

/**
 * 授予了给定权限码的角色（数据范围 self）。
 */
function roleWithCodes(string $name, string ...$codes): Role
{
    $role = Role::factory()->create(['name' => $name, 'title' => ucfirst($name)]);
    $role->givePermissionTo($codes);

    return $role;
}

/**
 * 测试用的范围 `vip`：只看 name 以 “VIP ” 开头的用户。
 */
function registerVipScope(): void
{
    resolve(DataScopeRegistry::class)->register([new class implements DataScopeProvider
    {
        public function key(): string
        {
            return 'vip';
        }

        public function label(): string
        {
            return 'VIP';
        }

        public function constrain(Builder $query, User $user, Collection $roles): void
        {
            $query->where('name', 'like', 'VIP %');
        }

        public function contains(Model $record, User $user, Collection $roles): bool
        {
            return str_starts_with((string) $record->getAttribute('name'), 'VIP ');
        }
    }]);
}

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/system/users')->assertUnauthorized();
    });

    it('returns 403 without the system.user code', function (): void {
        $this->actingAs(userWithPermissions('system.role'))
            ->getJson('/api/admin/system/users')
            ->assertForbidden()
            ->assertExactJson(['code' => 403, 'data' => null, 'message' => 'This action is unauthorized.']);
    });

    it('lists the user fields with their roles, newest first', function (): void {
        $actor = superAdminActor();
        $editor = roleWithCodes('editor', 'system');
        $auditor = Role::factory()->disabled()->create(['name' => 'auditor', 'title' => 'Auditor', 'sort' => -1]);
        $user = User::factory()->create(['username' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.com'])->assignRole($editor, $auditor);

        $this->actingAs($actor)
            ->getJson('/api/admin/system/users?pageSize=1')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['total' => 2, 'items' => [[
                'id' => $user->id,
                'username' => 'alice',
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'status' => 'enabled',
                'roles' => [['name' => 'auditor', 'title' => 'Auditor'], ['name' => 'editor', 'title' => 'Editor']],
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ]]]]);
    });

    it('loads the roles without a query per user', function (): void {
        $actor = superAdminActor();
        $role = roleWithCodes('editor', 'system');
        User::factory()->count(5)->create()->each(fn (User $user): User => $user->assignRole($role));

        // 第一次请求会加载权限缓存等，先预热
        $this->actingAs($actor)->getJson('/api/admin/system/users')->assertOk();

        DB::enableQueryLog();
        $this->actingAs($actor)->getJson('/api/admin/system/users')->assertOk()->assertJsonCount(6, 'data.items');
        $queries = count(DB::getQueryLog());

        User::factory()->count(5)->create()->each(fn (User $user): User => $user->assignRole($role));

        DB::flushQueryLog();
        $this->actingAs($actor)->getJson('/api/admin/system/users')->assertOk()->assertJsonCount(11, 'data.items');

        expect(count(DB::getQueryLog()))->toBe($queries);
    });

    it('shows the self scope only the operator and the users they created', function (): void {
        $actor = userWithPermissions('system.user');
        $own = User::factory()->create(['created_by' => $actor->id]);
        User::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/system/users')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.*.id', [$own->id, $actor->id]);
    });

    it('unions the scopes of multiple roles', function (): void {
        registerVipScope();
        $actor = userWithPermissions('system.user');
        $actor->assignRole(Role::factory()->create(['data_scope' => 'vip']));
        $own = User::factory()->create(['created_by' => $actor->id]);
        $vip = User::factory()->create(['name' => 'VIP Bob']);
        User::factory()->create();

        $this->actingAs($actor->fresh())
            ->getJson('/api/admin/system/users')
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$vip->id, $own->id, $actor->id]);
    });

    it('lists everyone for the all scope', function (): void {
        $actor = userWithDataScope('all', 'system.user');
        User::factory()->count(2)->create();

        $this->actingAs($actor)->getJson('/api/admin/system/users')->assertOk()->assertJsonPath('data.total', 3);
    });

    it('filters by username, name, email and status', function (string $query, array $usernames): void {
        User::factory()->create(['username' => 'alice', 'name' => 'Alice Liddell', 'email' => 'alice@example.com']);
        User::factory()->disabled()->create(['username' => 'bob', 'name' => 'Bob', 'email' => 'bob@test.org']);

        $this->actingAs(userWithDataScope('all', 'system.user'))
            ->getJson("/api/admin/system/users?{$query}")
            ->assertOk()
            ->assertJsonPath('data.items.*.username', $usernames);
    })->with([
        'username contains' => ['filter[username]=lic', ['alice']],
        'name contains' => ['filter[name]=liddell', ['alice']],
        'email contains' => ['filter[email]=test.org', ['bob']],
        'status equals' => ['filter[status]=disabled', ['bob']],
    ]);

    it('filters by a created_at range with a time zone offset', function (): void {
        $actor = userWithDataScope('all', 'system.user');
        $actor->forceFill(['created_at' => CarbonImmutable::parse('2026-01-01T00:00:00Z')])->save();
        $early = User::factory()->create(['created_at' => CarbonImmutable::parse('2026-11-19T01:00:00Z')]);
        $late = User::factory()->create(['created_at' => CarbonImmutable::parse('2026-11-19T03:00:00Z')]);

        $this->actingAs($actor)
            ->getJson('/api/admin/system/users?filter[created_at]='.urlencode('2026-11-19T09:00:00+08:00,2026-11-19T09:59:59+08:00'))
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$early->id]);

        $this->actingAs($actor)
            ->getJson('/api/admin/system/users?filter[created_at]='.urlencode('2026-11-19T02:00:00Z,'))
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$late->id]);
    });

    it('returns 422 for a malformed created_at range', function (): void {
        $this->actingAs(userWithDataScope('all', 'system.user'))
            ->getJson('/api/admin/system/users?filter[created_at]=2026-11-19,2026-11-20', ['Accept-Language' => 'zh-CN'])
            ->assertUnprocessable()
            ->assertExactJson(['code' => 422, 'data' => null, 'message' => '时间区间应使用带时区偏移的 ISO 8601 时间。', 'errors' => [
                'filter.created_at' => ['时间区间应使用带时区偏移的 ISO 8601 时间。'],
            ]]);
    });

    it('sorts by the allowed fields', function (): void {
        $actor = userWithDataScope('all', 'system.user');
        User::factory()->create(['username' => 'zed']);
        User::factory()->create(['username' => 'amy']);

        $this->actingAs($actor)
            ->getJson('/api/admin/system/users?sort=username&pageSize=2')
            ->assertOk()
            ->assertJsonPath('data.items.*.username', ['amy', $actor->username]); // 工厂的用户名以 user_ 开头
    });

    it('returns 422 for filters and sorts outside the allowlist', function (string $query, string $field, string $message): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/users?{$query}")
            ->assertUnprocessable()
            ->assertExactJson(['code' => 422, 'data' => null, 'message' => $message, 'errors' => [$field => [$message]]]);
    })->with([
        'filter' => ['filter[password]=x', 'filter', 'Unsupported filter: password.'],
        'sort' => ['sort=email', 'sort', 'Unsupported sort: email.'],
    ]);

    it('returns 422 for an invalid status filter', function (): void {
        $this->actingAs(superAdminActor())
            ->getJson('/api/admin/system/users?filter[status]=active')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter.status']);
    });
});

describe('show', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/system/users/1')->assertUnauthorized();
    });

    it('returns 403 without the system.user code', function (): void {
        $this->actingAs(userWithDataScope('all', 'system.role'))
            ->getJson('/api/admin/system/users/'.User::factory()->create()->id)
            ->assertForbidden();
    });

    it('returns 403 outside the data scope', function (): void {
        $this->actingAs(userWithPermissions('system.user'))
            ->getJson('/api/admin/system/users/'.User::factory()->create()->id)
            ->assertForbidden();
    });

    it('returns the user with its roles', function (): void {
        $actor = userWithPermissions('system.user');
        $user = User::factory()->create(['created_by' => $actor->id])->assignRole(roleWithCodes('editor', 'system'));

        $this->actingAs($actor)
            ->getJson("/api/admin/system/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.username', $user->username)
            ->assertJsonPath('data.roles', [['name' => 'editor', 'title' => 'Editor']]);
    });

    it('shows the operator to the self scope', function (): void {
        $actor = userWithPermissions('system.user');

        $this->actingAs($actor)->getJson("/api/admin/system/users/{$actor->id}")->assertOk()->assertJsonPath('data.id', $actor->id);
    });

    it('returns 404 for unknown and malformed ids', function (string $id): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/users/{$id}")
            ->assertNotFound()
            ->assertJsonPath('code', 404);
    })->with(['missing' => '999999', 'not a number' => 'abc']);
});

describe('store', function (): void {
    beforeEach(function (): void {
        $this->payload = ['username' => 'Alice', 'name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'secret-password'];
    });

    it('returns 401 for guests', function (): void {
        $this->postJson('/api/admin/system/users', $this->payload)->assertUnauthorized();
    });

    it('returns 403 without the system.user.create code', function (): void {
        $this->actingAs(userWithPermissions('system.user', 'system.user.update'))
            ->postJson('/api/admin/system/users', $this->payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'alice']);
    });

    it('creates the user with a lowercase username, the creator and the roles', function (): void {
        $actor = userWithPermissions('system.user.create', 'system.user');
        roleWithCodes('viewer', 'system', 'system.user');

        $response = $this->actingAs($actor)->postJson('/api/admin/system/users', [...$this->payload, 'roles' => ['viewer']]);

        $user = User::query()->where('username', 'alice')->sole();

        $response->assertCreated()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', 'alice')
            ->assertJsonPath('data.status', 'enabled')
            ->assertJsonPath('data.roles', [['name' => 'viewer', 'title' => 'Viewer']])
            ->assertJsonMissingPath('data.password');
        expect($user->created_by)->toBe($actor->id)
            ->and(Hash::check('secret-password', $user->password))->toBeTrue()
            ->and(assignedRoles($user))->toBe(['viewer']);
    });

    it('lets the creator with the self scope see the new user', function (): void {
        $actor = userWithPermissions('system.user', 'system.user.create');

        $id = $this->actingAs($actor)->postJson('/api/admin/system/users', $this->payload)->assertCreated()->json('data.id');

        $this->actingAs($actor)->getJson("/api/admin/system/users/{$id}")->assertOk();
    });

    it('saves a disabled user without email', function (): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/users', [...$this->payload, 'email' => null, 'status' => 'disabled'])
            ->assertCreated()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonPath('data.roles', []);
    });

    it('requires username, name and password', function (): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'name', 'password']);
    });

    it('rejects invalid fields', function (array $payload, string $field): void {
        User::factory()->create(['username' => 'bob', 'email' => 'bob@example.com']);

        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/users', [...$this->payload, ...$payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertDatabaseMissing('users', ['name' => 'Alice']);
    })->with([
        'username with spaces' => [['username' => 'alice smith'], 'username'],
        'username taken, in another case' => [['username' => 'BOB'], 'username'],
        'username too long' => [['username' => str_repeat('a', 65)], 'username'],
        'email invalid' => [['email' => 'not-an-email'], 'email'],
        'email taken' => [['email' => 'bob@example.com'], 'email'],
        'password too short' => [['password' => 'short'], 'password'],
        'unknown status' => [['status' => 'active'], 'status'],
        'roles not a list' => [['roles' => 'editor'], 'roles'],
        'unknown role' => [['roles' => ['ghost']], 'roles.0'],
        'unknown field' => [['created_by' => 1], 'created_by'],
    ]);

    it('localizes the field names in validation messages', function (): void {
        User::factory()->create(['username' => 'alice']);

        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/users', $this->payload, ['Accept-Language' => 'zh-CN'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', '用户名 已存在。');
    });

    it('returns 422 when an operator who is not a super admin grants the super admin role', function (): void {
        $this->actingAs(userWithDataScope('all', 'system.user.create'))
            ->postJson('/api/admin/system/users', [...$this->payload, 'roles' => [Role::SUPER_ADMIN]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.roles.0', 'Only super admins can grant or revoke the super admin role.');

        $this->assertDatabaseMissing('users', ['username' => 'alice']);
    });

    it('returns 422 for roles with codes that the operator does not have', function (): void {
        roleWithCodes('viewer', 'system', 'system.user');
        roleWithCodes('menu-admin', 'system', 'system.menu', 'system.menu.delete');

        $this->actingAs(userWithPermissions('system.user.create'))
            ->postJson('/api/admin/system/users', [...$this->payload, 'roles' => ['viewer', 'menu-admin']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.roles.0', 'You cannot assign roles with permissions or data scopes you do not have: menu-admin.');

        $this->assertDatabaseMissing('users', ['username' => 'alice']);
    });

    it('lets super admins assign any role', function (): void {
        roleWithCodes('menu-admin', 'system', 'system.menu');

        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/users', [...$this->payload, 'roles' => [Role::SUPER_ADMIN, 'menu-admin']])
            ->assertCreated();

        expect(assignedRoles(User::query()->where('username', 'alice')->sole()))->toBe(['menu-admin', Role::SUPER_ADMIN]);
    });
});

describe('update', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create(['username' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.com']);
        $this->payload = ['username' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.com'];
    });

    it('returns 401 for guests', function (): void {
        $this->putJson("/api/admin/system/users/{$this->user->id}", $this->payload)->assertUnauthorized();
    });

    it('returns 403 without the system.user.update code', function (): void {
        $this->actingAs(userWithDataScope('all', 'system.user', 'system.user.create'))
            ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'name' => 'Changed'])
            ->assertForbidden();

        expect($this->user->fresh()?->name)->toBe('Alice');
    });

    it('returns 403 outside the data scope', function (): void {
        $this->actingAs(userWithPermissions('system.user.update'))
            ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'name' => 'Changed'])
            ->assertForbidden();
    });

    it('returns 403 to operators who are not super admins for super admin users', function (): void {
        $superAdmin = superAdminActor();

        $this->actingAs(userWithDataScope('all', 'system.user.update'))
            ->putJson("/api/admin/system/users/{$superAdmin->id}", ['username' => $superAdmin->username, 'name' => 'Changed'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only super admins can manage super admin users.');
    });

    it('updates the fields and keeps the roles when none are submitted', function (): void {
        $this->user->assignRole(roleWithCodes('editor', 'system'));

        $this->actingAs(userWithDataScope('all', 'system.user.update'))
            ->putJson("/api/admin/system/users/{$this->user->id}", ['username' => 'Alice2', 'name' => 'Alice Liddell', 'email' => null])
            ->assertOk()
            ->assertJsonPath('data.username', 'alice2')
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.roles', [['name' => 'editor', 'title' => 'Editor']]);

        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'username' => 'alice2', 'name' => 'Alice Liddell', 'email' => null]);
    });

    it('lets the self scope edit the operator', function (): void {
        $actor = userWithPermissions('system.user.update');

        $this->actingAs($actor)
            ->putJson("/api/admin/system/users/{$actor->id}", ['username' => $actor->username, 'name' => 'Me'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Me');
    });

    it('does not accept a password', function (): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'password' => 'new-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    it('rejects the username and email of another user', function (string $field, string $value): void {
        User::factory()->create(['username' => 'bob', 'email' => 'bob@example.com']);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, $field => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'username' => ['username', 'Bob'],
        'email' => ['email', 'bob@example.com'],
    ]);

    describe('roles', function (): void {
        beforeEach(function (): void {
            $this->viewer = roleWithCodes('viewer', 'system', 'system.user');
            $this->menuAdmin = roleWithCodes('menu-admin', 'system', 'system.menu');
            $this->operator = userWithDataScope('all', 'system.user', 'system.user.update');
        });

        it('replaces the roles', function (): void {
            $this->user->assignRole($this->menuAdmin);

            $this->actingAs(superAdminActor())
                ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'roles' => ['viewer']])
                ->assertOk()
                ->assertJsonPath('data.roles', [['name' => 'viewer', 'title' => 'Viewer']]);

            expect(assignedRoles($this->user))->toBe(['viewer']);
        });

        it('lets the operator assign and remove the roles within their codes', function (): void {
            $this->actingAs($this->operator)
                ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'roles' => ['viewer']])
                ->assertOk();
            expect(assignedRoles($this->user))->toBe(['viewer']);

            $this->actingAs($this->operator)
                ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'roles' => []])
                ->assertOk();
            expect(assignedRoles($this->user))->toBe([]);
        });

        it('rejects adding roles with codes that the operator does not have', function (): void {
            $this->actingAs($this->operator)
                ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'roles' => ['viewer', 'menu-admin']])
                ->assertUnprocessable()
                ->assertJsonPath('errors.roles.0', 'You cannot assign roles with permissions or data scopes you do not have: menu-admin.');

            expect(assignedRoles($this->user))->toBe([]);
        });

        it('returns 403 for a user holding a role that the operator cannot manage, whether submitted or not', function (array $submitted): void {
            $this->user->assignRole($this->menuAdmin);

            $this->actingAs($this->operator)
                ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'roles' => $submitted])
                ->assertForbidden()
                ->assertJsonPath('message', 'You cannot manage users with permissions or data scopes beyond your own.');

            expect(assignedRoles($this->user))->toBe(['menu-admin']);
        })->with([
            'not submitted' => [['viewer']],
            'submitted' => [['viewer', 'menu-admin']],
        ]);

        it('rejects adding roles with a data scope that the operator cannot grant', function (): void {
            $this->viewer->update(['data_scope' => 'all']);

            $own = User::factory()->create(['created_by' => ($actor = userWithPermissions('system.user', 'system.user.update'))->id]);

            $this->actingAs($actor)
                ->putJson("/api/admin/system/users/{$own->id}", ['username' => $own->username, 'name' => $own->name, 'roles' => ['viewer']])
                ->assertUnprocessable()
                ->assertJsonPath('errors.roles.0', 'You cannot assign roles with permissions or data scopes you do not have: viewer.');

            expect(assignedRoles($own))->toBe([]);
        });

        it('returns 403 for a user holding a role with a data scope that the operator cannot grant', function (): void {
            $this->viewer->update(['data_scope' => 'all']);
            $actor = userWithPermissions('system.user', 'system.user.update');
            $own = User::factory()->create(['created_by' => $actor->id])->assignRole($this->viewer);
            roleWithCodes('self-viewer', 'system', 'system.user');

            $this->actingAs($actor)
                ->putJson("/api/admin/system/users/{$own->id}", ['username' => $own->username, 'name' => $own->name, 'roles' => ['self-viewer']])
                ->assertForbidden();

            expect(assignedRoles($own))->toBe(['viewer']);
        });

        it('lets super admins assign roles with any data scope', function (): void {
            $this->viewer->update(['data_scope' => 'all']);

            $this->actingAs(superAdminActor())
                ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'roles' => ['viewer']])
                ->assertOk();

            expect(assignedRoles($this->user))->toBe(['viewer']);
        });

        it('returns 422 when an operator who is not a super admin grants the super admin role', function (): void {
            $this->actingAs($this->operator)
                ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'roles' => [Role::SUPER_ADMIN]])
                ->assertUnprocessable()
                ->assertJsonPath('errors.roles.0', 'Only super admins can grant or revoke the super admin role.');
        });

        it('lets a super admin remove their own super admin role while another one remains', function (): void {
            $actor = superAdminActor();
            superAdminActor();

            $this->actingAs($actor)
                ->putJson("/api/admin/system/users/{$actor->id}", ['username' => $actor->username, 'name' => $actor->name, 'roles' => []])
                ->assertOk();

            expect(assignedRoles($actor))->toBe([]);
        });

        it('returns 422 when removing the super admin role from the last enabled super admin', function (): void {
            $actor = superAdminActor();
            User::factory()->disabled()->create()->assignRole(Role::SUPER_ADMIN);

            $this->actingAs($actor)
                ->putJson("/api/admin/system/users/{$actor->id}", ['username' => $actor->username, 'name' => 'Changed', 'roles' => ['viewer']], ['Accept-Language' => 'zh-CN'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.roles.0', '不能移除最后一个启用的超级管理员的超级管理员角色。');

            expect(assignedRoles($actor))->toBe([Role::SUPER_ADMIN])
                ->and($actor->fresh()?->name)->not->toBe('Changed');
        });
    });

    describe('status', function (): void {
        it('disables the user and revokes their sessions and tokens', function (): void {
            config(['session.driver' => 'database']);
            DB::table('sessions')->insert(['id' => 'alice', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
            $this->user->createToken('device');

            $this->actingAs(userWithDataScope('all', 'system.user.update'))
                ->putJson("/api/admin/system/users/{$this->user->id}", [...$this->payload, 'status' => 'disabled'])
                ->assertOk()
                ->assertJsonPath('data.status', 'disabled');

            expect(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0)
                ->and($this->user->tokens()->count())->toBe(0);
        });

        it('returns 422 when disabling yourself', function (): void {
            $actor = userWithPermissions('system.user.update');

            $this->actingAs($actor)
                ->putJson("/api/admin/system/users/{$actor->id}", ['username' => $actor->username, 'name' => $actor->name, 'status' => 'disabled'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.status.0', 'You cannot disable yourself.');

            expect($actor->fresh()?->isEnabled())->toBeTrue();
        });
    });
});

describe('destroy', function (): void {
    it('returns 401 for guests', function (): void {
        $this->deleteJson('/api/admin/system/users/'.User::factory()->create()->id)->assertUnauthorized();
    });

    it('returns 403 without the system.user.delete code', function (): void {
        $user = User::factory()->create();

        $this->actingAs(userWithDataScope('all', 'system.user', 'system.user.update'))
            ->deleteJson("/api/admin/system/users/{$user->id}")
            ->assertForbidden();

        $this->assertModelExists($user);
    });

    it('returns 403 outside the data scope', function (): void {
        $user = User::factory()->create();

        $this->actingAs(userWithPermissions('system.user.delete'))->deleteJson("/api/admin/system/users/{$user->id}")->assertForbidden();

        $this->assertModelExists($user);
    });

    it('returns 403 for yourself', function (): void {
        $actor = userWithPermissions('system.user.delete');

        $this->actingAs($actor)
            ->deleteJson("/api/admin/system/users/{$actor->id}", [], ['Accept-Language' => 'zh-CN'])
            ->assertForbidden()
            ->assertExactJson(['code' => 403, 'data' => null, 'message' => '不能删除自己。']);
    });

    it('returns 403 to operators who are not super admins for super admin users', function (): void {
        $superAdmin = superAdminActor();

        $this->actingAs(userWithDataScope('all', 'system.user.delete'))
            ->deleteJson("/api/admin/system/users/{$superAdmin->id}")
            ->assertForbidden();

        $this->assertModelExists($superAdmin);
    });

    it('deletes the user with their roles, sessions and tokens', function (): void {
        config(['session.driver' => 'database']);
        $actor = userWithPermissions('system.user.delete');
        $user = User::factory()->create(['created_by' => $actor->id])->assignRole(roleWithCodes('editor', 'system'));
        $created = User::factory()->create(['created_by' => $user->id]);
        DB::table('sessions')->insert(['id' => 'user', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        $user->createToken('device');

        $this->actingAs($actor)
            ->deleteJson("/api/admin/system/users/{$user->id}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        $this->assertModelMissing($user);
        $this->assertDatabaseMissing('model_has_roles', ['model_type' => 'user', 'model_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        expect($created->fresh()?->created_by)->toBeNull();
    });

    it('lets super admins delete another super admin', function (): void {
        $superAdmin = superAdminActor();

        $this->actingAs(superAdminActor())->deleteJson("/api/admin/system/users/{$superAdmin->id}")->assertOk();

        $this->assertModelMissing($superAdmin);
    });
});
