<?php

use App\Core\System\Actions\{SyncRolePermissions, SyncUserRoles};
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Permission, Role, User};
use App\Core\System\Services\{Manageability, PermissionRegistry};

/*
|--------------------------------------------------------------------------
| “管得到”的统一判定（设计文档 §8.5）
|--------------------------------------------------------------------------
|
| 接口上的矩阵见 tests/Http/Admin/ManageabilityTest.php；这里覆盖判定本身的各个条件，
| 以及分配角色、授予权限码时“管不到的保持不变”（Policy 先拦住管不到的目标，这条规则只在并发改动时起作用）。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->manageability = resolve(Manageability::class);
});

/**
 * 授予给定权限码（及其祖先）的角色。
 *
 * @param  array<string, mixed>  $attributes
 */
function roleGranting(array $attributes, string ...$codes): Role
{
    $role = Role::factory()->create($attributes);
    $role->givePermissionTo(resolve(PermissionRegistry::class)->withAncestors($codes));

    return $role->load('permissions');
}

describe('roles', function (): void {
    it('manages a role whose codes and data scope are within the operator', function (string $scope): void {
        $actor = userWithDataScope($scope, 'system.user.export', 'system.user.delete');

        expect($this->manageability->canManageRole($actor, roleGranting(['data_scope' => 'self'], 'system.user.export')))->toBeTrue();
    })->with(['self', 'all']);

    it('does not manage a role beyond the operator', function (Closure $role): void {
        $actor = userWithPermissions('system.user.export');

        expect($this->manageability->canManageRole($actor, $role()))->toBeFalse();
    })->with([
        'one code beyond' => fn (): Role => roleGranting([], 'system.user.export', 'system.user.delete'),
        'data scope beyond (codes × scope)' => fn (): Role => roleGranting(['data_scope' => 'all'], 'system.user.export'),
        'unregistered data scope' => fn (): Role => roleGranting(['data_scope' => 'department'], 'system.user.export'),
        'a disabled role with codes beyond' => fn (): Role => roleGranting(['status' => Status::Disabled], 'system.user.delete'),
        'the super admin role' => fn (): Role => Role::query()->where('name', Role::SUPER_ADMIN)->sole(),
    ]);

    it('does not manage a role with a code on a disabled node, even one the operator was granted', function (string $disabled): void {
        $actor = userWithPermissions('system.user.export');
        $role = roleGranting([], 'system.user.export');
        Permission::query()->where('name', $disabled)->sole()->update(['status' => Status::Disabled]);
        app()->forgetScopedInstances();

        expect(resolve(Manageability::class)->canManageRole($actor->fresh(), $role))->toBeFalse();
    })->with(['the node' => 'system.user.export', 'an ancestor' => 'system.user']);

    it('does not count the codes of the operator\'s disabled roles', function (): void {
        $actor = userWithPermissions('system.role.update');
        $actor->assignRole(roleGranting(['status' => Status::Disabled], 'system.user.delete'));

        expect($this->manageability->canManageRole($actor->fresh(), roleGranting([], 'system.user.delete')))->toBeFalse();
    });

    it('lets super admins manage every role', function (Closure $role): void {
        expect($this->manageability->canManageRole(superAdminActor(), $role()))->toBeTrue();
    })->with([
        'codes beyond' => fn (): Role => roleGranting(['data_scope' => 'all', 'status' => Status::Disabled], 'system.user.delete'),
        'the super admin role' => fn (): Role => Role::query()->where('name', Role::SUPER_ADMIN)->sole(),
    ]);
});

describe('users', function (): void {
    it('manages a user whose roles are all manageable and who is in the data scope', function (): void {
        $actor = userWithDataScope('all', 'system.user.export');
        $user = User::factory()->create()->assignRole(roleGranting([], 'system.user.export'));

        expect($this->manageability->canManageUser($actor, $user))->toBeTrue()
            ->and($this->manageability->canManageUser($actor, User::factory()->create()))->toBeTrue();
    });

    it('does not manage a user beyond the operator', function (Closure $user): void {
        $actor = userWithDataScope('all', 'system.user.export');

        expect($this->manageability->canManageUser($actor, $user()))->toBeFalse();
    })->with([
        'a super admin' => superAdminActor(...),
        'a disabled account with the super admin role' => fn (): User => User::factory()->disabled()->create()->assignRole(Role::SUPER_ADMIN),
        'one role beyond' => fn (): User => User::factory()->create()->assignRole(roleGranting([], 'system.user.export'), roleGranting([], 'system.role.update')),
        'a disabled role beyond' => fn (): User => User::factory()->create()->assignRole(roleGranting(['status' => Status::Disabled], 'system.role.update')),
    ]);

    it('does not manage a user outside the data scope', function (): void {
        $actor = userWithPermissions('system.user.export');

        expect($this->manageability->canManageUser($actor, User::factory()->create()))->toBeFalse()
            ->and($this->manageability->canManageUser($actor, User::factory()->create(['created_by' => $actor->id])))->toBeTrue();
    });

    it('lets super admins manage every user', function (): void {
        $user = User::factory()->create()->assignRole(roleGranting(['data_scope' => 'all'], 'system.role.update'));

        expect($this->manageability->canManageUser(superAdminActor(), $user))->toBeTrue()
            ->and($this->manageability->canManageUser(superAdminActor(), superAdminActor()))->toBeTrue();
    });
});

describe('keeping what the operator cannot manage', function (): void {
    it('keeps the roles of a user that the operator cannot manage when assigning roles', function (): void {
        $actor = userWithDataScope('all', 'system.user.update');
        $viewer = roleGranting([], 'system.user');
        $menuAdmin = roleGranting([], 'system.menu');
        $user = User::factory()->create()->assignRole($menuAdmin);

        // 例如：读取用户之后，另一位管理员给他分配了管不到的角色
        resolve(SyncUserRoles::class)->handle($user, [$viewer->name], $actor);

        expect($user->roles()->pluck('name')->sort()->values()->all())->toBe(collect([$viewer->name, $menuAdmin->name])->sort()->values()->all());
    });

    it('keeps the codes of a role that the operator does not have when granting codes', function (): void {
        $actor = userWithPermissions('system.role.update', 'system.user');
        $role = roleGranting([], 'system.user.create');

        resolve(SyncRolePermissions::class)->handle($role, ['system', 'system.role'], $actor);

        expect($role->permissions()->pluck('name')->sort()->values()->all())->toBe(['system', 'system.role', 'system.user', 'system.user.create']);
    });
});
