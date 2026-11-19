<?php

use App\Core\System\Models\Role;
use App\Core\System\Policies\RolePolicy;
use App\Core\System\Services\PermissionRegistry;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| RolePolicy：每个方法要求的权限码与业务保护（设计文档 §6.5、§8.3、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->role = Role::factory()->create();
});

/**
 * 方法 => 允许它的权限码（任一即可）。
 */
dataset('abilities', [
    'viewAny' => ['viewAny', false, ['system.role']],
    'view' => ['view', true, ['system.role']],
    'create' => ['create', false, ['system.role.create']],
    'update' => ['update', true, ['system.role.update']],
    'delete' => ['delete', true, ['system.role.delete']],
    'changeStatus' => ['changeStatus', true, ['system.role.status']],
    'viewFormOptions' => ['viewFormOptions', false, ['system.role.create', 'system.role.update']],
    'viewOptions' => ['viewOptions', false, ['system.user.create', 'system.user.update']],
]);

it('allows each ability only with its permission codes', function (string $ability, bool $onRecord, array $codes): void {
    // 其余全部权限码（不含这些码的子孙：授予子孙会补齐祖先）
    $others = array_filter(
        resolve(PermissionRegistry::class)->registeredCodes(),
        fn (string $other): bool => ! array_any($codes, fn (string $code): bool => $other === $code || str_starts_with($other, "{$code}.")),
    );
    $arguments = $onRecord ? $this->role : Role::class;

    expect(Gate::forUser(userWithPermissions(...$others))->allows($ability, $arguments))->toBeFalse();

    foreach ($codes as $code) {
        expect(Gate::forUser(userWithPermissions($code))->allows($ability, $arguments))->toBeTrue("{$ability} 应允许 {$code}");
    }
})->with('abilities');

it('denies the abilities that roles do not support, even to super admins', function (string $ability, bool $onRecord): void {
    expect(Gate::forUser(superAdminActor())->allows($ability, $onRecord ? $this->role : Role::class))->toBeFalse();
})->with([
    'restore' => ['restore', true],
    'forceDelete' => ['forceDelete', true],
    'deleteAny' => ['deleteAny', false],
    'restoreAny' => ['restoreAny', false],
    'forceDeleteAny' => ['forceDeleteAny', false],
]);

it('forbids deleting the super admin role with a message', function (): void {
    $response = Gate::forUser(superAdminActor())->inspect('delete', Role::query()->where('name', Role::SUPER_ADMIN)->sole());

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe('The super admin role cannot be deleted.');
});

it('is the policy of the Role model', function (): void {
    expect(Gate::getPolicyFor(Role::class))->toBeInstanceOf(RolePolicy::class);
});
