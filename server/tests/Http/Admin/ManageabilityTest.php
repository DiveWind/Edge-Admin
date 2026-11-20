<?php

use App\Core\Monitor\Models\Session;
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Permission, Role, User};
use App\Core\System\Services\EffectivePermissions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| “管得到”（设计文档 §8.5）：非超管只能管理权限码与数据范围都不超过自己的角色和用户
|--------------------------------------------------------------------------
|
| 矩阵：角色的修改 / 删除 / 启停，用户的修改 / 删除 / 启停 / 重置密码，在线会话的强制下线、token 的吊销，
| 分别对“管得到”“码超出”“范围超出”“码在停用节点上”“停用角色的码超出”的目标，比较超管与非超管。
| 管不到 → 403（统一文案）；超管不受限。各接口的其余规则见各自的控制器测试。
|
*/

const MANAGE_ROLE_DENIED = 'You cannot manage roles with permissions or data scopes beyond your own.';
const MANAGE_USER_DENIED = 'You cannot manage users with permissions or data scopes beyond your own.';

beforeEach(function (): void {
    config(['session.driver' => 'database']);
    syncBuiltinPermissions();
});

/**
 * 授予给定权限码（及其祖先）的角色。
 *
 * @param  array<string, mixed>  $attributes
 */
function manageabilityRole(array $attributes, string ...$codes): Role
{
    $role = Role::factory()->create($attributes);
    $role->givePermissionTo(resolve(EffectivePermissions::class)->withAncestors($codes));

    return $role;
}

/**
 * 停用节点（之后的判定按新数据计算）。
 */
function disableNode(string $code): void
{
    Permission::query()->where('name', $code)->sole()->update(['status' => Status::Disabled]);
    app()->forgetScopedInstances();
}

/*
|--------------------------------------------------------------------------
| 角色
|--------------------------------------------------------------------------
*/

/**
 * 角色管理的写操作，返回响应。
 *
 * @return array<string, Closure(TestCase, Role): TestResponse>
 */
function roleOperations(): array
{
    return [
        'update' => fn (TestCase $test, Role $role): TestResponse => $test->putJson("/api/admin/system/roles/{$role->id}", ['name' => $role->name, 'title' => 'Changed']),
        'delete' => fn (TestCase $test, Role $role): TestResponse => $test->deleteJson("/api/admin/system/roles/{$role->id}"),
        'status' => fn (TestCase $test, Role $role): TestResponse => $test->putJson("/api/admin/system/roles/{$role->id}/status", ['status' => $role->isEnabled() ? 'disabled' : 'enabled']),
    ];
}

/**
 * 非超管的角色管理员：范围 self，持有角色管理的写权限码与 system.user.export。
 */
function roleManager(): User
{
    return userWithPermissions('system.role.update', 'system.role.delete', 'system.role.status', 'system.user.export');
}

describe('roles', function (): void {
    it('lets operators manage roles within their own codes and data scope', function (Closure $operation): void {
        $role = manageabilityRole(['data_scope' => 'self'], 'system.user.export');

        $operation($this->actingAs(roleManager()), $role)->assertOk();
    })->with(roleOperations());

    it('returns 403 for roles beyond the operator', function (Closure $operation, Closure $target): void {
        $actor = roleManager();
        $role = $target($actor);
        $before = [$role->title, $role->status, $role->permissions()->count()];

        $operation($this->actingAs($actor), $role)
            ->assertForbidden()
            ->assertJsonPath('message', MANAGE_ROLE_DENIED);

        $role = $role->fresh();
        expect($role)->not->toBeNull()
            ->and([$role->title, $role->status, $role->permissions()->count()])->toBe($before);
    })->with(roleOperations())->with([
        'codes beyond' => fn (User $actor): Role => manageabilityRole([], 'system.user.delete'),
        'data scope beyond' => fn (User $actor): Role => manageabilityRole(['data_scope' => 'all'], 'system.user.export'),
        'unregistered data scope' => fn (User $actor): Role => manageabilityRole(['data_scope' => 'department'], 'system.user.export'),
        'a code on a disabled node' => function (User $actor): Role {
            $role = manageabilityRole([], 'system.user.export');
            disableNode('system.user.export');

            return $role;
        },
        'a disabled role of the operator with codes beyond' => function (User $actor): Role {
            $role = manageabilityRole(['status' => Status::Disabled, 'data_scope' => 'all'], 'system.user.delete');
            $actor->assignRole($role);

            return $role;
        },
    ]);

    it('does not let operators enable a disabled role they hold to gain its codes', function (string $route): void {
        $actor = userWithPermissions('system.role.status', 'system.role.update');
        $role = manageabilityRole(['status' => Status::Disabled, 'data_scope' => 'all'], 'system.user.delete');
        $actor->assignRole($role);

        $response = $route === 'status'
            ? $this->actingAs($actor)->putJson("/api/admin/system/roles/{$role->id}/status", ['status' => 'enabled'])
            : $this->actingAs($actor)->putJson("/api/admin/system/roles/{$role->id}", ['name' => $role->name, 'title' => 'x', 'status' => 'enabled']);

        $response->assertForbidden();
        app()->forgetScopedInstances();
        expect($role->fresh()?->status)->toBe(Status::Disabled)
            ->and($actor->fresh()?->can('system.user.delete'))->toBeFalse();
    })->with(['status', 'update']);

    it('lets super admins manage any role', function (Closure $operation, Closure $target): void {
        $operation($this->actingAs(superAdminActor()), $target())->assertOk();
    })->with(roleOperations())->with([
        'codes beyond' => fn (): Role => manageabilityRole([], 'system.user.delete'),
        'data scope all' => fn (): Role => manageabilityRole(['data_scope' => 'all'], 'system.user.export'),
        'a code on a disabled node' => function (): Role {
            $role = manageabilityRole([], 'system.user.export');
            disableNode('system.user.export');

            return $role;
        },
    ]);

    it('still lets operators view roles they cannot manage', function (): void {
        $role = manageabilityRole(['data_scope' => 'all'], 'system.user.delete');

        $this->actingAs(userWithPermissions('system.role'))
            ->getJson("/api/admin/system/roles/{$role->id}")
            ->assertOk();
    });
});

/*
|--------------------------------------------------------------------------
| 用户、在线会话与 token
|--------------------------------------------------------------------------
*/

/**
 * 对某个用户的写操作（用户管理、强制下线），返回响应。
 *
 * @return array<string, Closure(TestCase, User): TestResponse>
 */
function userOperations(): array
{
    return [
        'update' => fn (TestCase $test, User $user): TestResponse => $test->putJson("/api/admin/system/users/{$user->id}", ['username' => $user->username, 'name' => 'Changed', 'email' => 'attacker@example.com']),
        'delete' => fn (TestCase $test, User $user): TestResponse => $test->deleteJson("/api/admin/system/users/{$user->id}"),
        'status' => fn (TestCase $test, User $user): TestResponse => $test->putJson("/api/admin/system/users/{$user->id}/status", ['status' => 'disabled']),
        'reset password' => fn (TestCase $test, User $user): TestResponse => $test->putJson("/api/admin/system/users/{$user->id}/password", ['password' => 'new-password']),
        'log out a session' => fn (TestCase $test, User $user): TestResponse => $test->deleteJson('/api/admin/monitor/online/sessions/'.Session::factory()->for($user)->create()->publicId()),
        'revoke a token' => fn (TestCase $test, User $user): TestResponse => $test->deleteJson('/api/admin/monitor/online/tokens/'.$user->createToken('device')->accessToken->getKey()),
    ];
}

/**
 * 非超管的用户管理员：范围 all（目标都在数据范围内），持有用户管理与强制下线的写权限码与 system.user.export。
 */
function userManager(string $scope = 'all'): User
{
    return userWithDataScope($scope, 'system.user.update', 'system.user.delete', 'system.user.status', 'system.user.password', 'monitor.online.logout', 'system.user.export');
}

describe('users', function (): void {
    it('lets operators manage users whose roles they can manage', function (Closure $operation, Closure $roles): void {
        $user = User::factory()->create();
        $user->assignRole($roles());

        $operation($this->actingAs(userManager()), $user)->assertOk();
    })->with(userOperations())->with([
        'no role' => fn (): array => [],
        'a role within the operator' => fn (): array => [manageabilityRole(['data_scope' => 'all'], 'system.user.export')],
    ]);

    it('returns 403 for users beyond the operator', function (Closure $operation, Closure $target): void {
        [$actor, $user] = $target();
        $before = [$user->name, $user->email, $user->status, $user->password];

        $operation($this->actingAs($actor), $user)
            ->assertForbidden()
            ->assertJsonPath('message', MANAGE_USER_DENIED);

        $user = $user->fresh();
        expect($user)->not->toBeNull()
            ->and([$user->name, $user->email, $user->status, $user->password])->toBe($before);
    })->with(userOperations())->with([
        'a role with codes beyond' => fn (): array => [userManager(), User::factory()->create()->assignRole(manageabilityRole([], 'system.role.update'))],
        'a role with a data scope beyond' => function (): array {
            $actor = userManager('self');

            return [$actor, User::factory()->create(['created_by' => $actor->id])->assignRole(manageabilityRole(['data_scope' => 'all'], 'system.user.export'))];
        },
        'a role with a code on a disabled node' => function (): array {
            $actor = userManager();
            $user = User::factory()->create()->assignRole(manageabilityRole([], 'system.user.export'));
            disableNode('system.user.export');

            return [$actor, $user];
        },
        'a disabled role with codes beyond' => fn (): array => [userManager(), User::factory()->create()->assignRole(manageabilityRole(['status' => Status::Disabled], 'system.menu.update'))],
    ]);

    it('lets super admins manage users with any roles', function (Closure $operation): void {
        $user = User::factory()->create()->assignRole(manageabilityRole(['data_scope' => 'all', 'status' => Status::Disabled], 'system.role.update', 'system.menu.update'));

        $operation($this->actingAs(superAdminActor()), $user)->assertOk();
    })->with(userOperations());

    it('keeps the earlier 403 for super admin users and users outside the data scope', function (Closure $operation): void {
        $actor = userManager('self');

        $operation($this->actingAs($actor), superAdminActor())->assertForbidden()->assertJsonPath('message', 'Only super admins can manage super admin users.');
        $operation($this->actingAs($actor), User::factory()->create())->assertForbidden()->assertJsonPath('message', 'This action is unauthorized.');
    })->with(userOperations());

    it('lets operators log out their own other sessions and tokens even when they cannot manage themselves', function (): void {
        $actor = userManager();
        $actor->assignRole(manageabilityRole(['status' => Status::Disabled], 'system.menu.update'));

        $this->actingAs($actor)->deleteJson('/api/admin/monitor/online/sessions/'.Session::factory()->for($actor)->create()->publicId())->assertOk();
        $this->actingAs($actor)->deleteJson('/api/admin/monitor/online/tokens/'.$actor->createToken('device')->accessToken->getKey())->assertOk();
        $this->actingAs($actor)->putJson("/api/admin/system/users/{$actor->id}", ['username' => $actor->username, 'name' => 'Changed'])->assertForbidden();
    });

    it('still lets operators view users they cannot manage', function (): void {
        $user = User::factory()->create()->assignRole(manageabilityRole([], 'system.role.update'));

        $this->actingAs(userWithDataScope('all', 'system.user'))
            ->getJson("/api/admin/system/users/{$user->id}")
            ->assertOk();
    });
});
