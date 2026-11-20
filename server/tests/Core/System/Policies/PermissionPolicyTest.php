<?php

use App\Core\System\Models\Permission;
use App\Core\System\Policies\PermissionPolicy;
use App\Core\System\Services\EffectivePermissions;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| PermissionPolicy：每个方法要求的权限码与业务保护（设计文档 §6.5、§8.3、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->node = Permission::factory()->create();
});

/**
 * 方法 => 允许它的权限码。
 */
dataset('abilities', [
    'viewAny' => ['viewAny', false, 'system.menu'],
    'view' => ['view', true, 'system.menu'],
    'create' => ['create', false, 'system.menu.create'],
    'update' => ['update', true, 'system.menu.update'],
    'delete' => ['delete', true, 'system.menu.delete'],
]);

it('allows each ability only with its permission code', function (string $ability, bool $onRecord, string $code): void {
    // 其余全部权限码（不含这个码的子孙：授予子孙会补齐祖先）
    $others = array_filter(
        resolve(EffectivePermissions::class)->registeredCodes(),
        fn (string $other): bool => $other !== $code && ! str_starts_with($other, "{$code}.") && $other !== $this->node->name,
    );
    $arguments = $onRecord ? $this->node : Permission::class;

    expect(Gate::forUser(userWithPermissions(...$others))->allows($ability, $arguments))->toBeFalse()
        ->and(Gate::forUser(userWithPermissions($code))->allows($ability, $arguments))->toBeTrue();
})->with('abilities');

it('denies the abilities that menu nodes do not support, even to super admins', function (string $ability, bool $onRecord): void {
    expect(Gate::forUser(superAdminActor())->allows($ability, $onRecord ? $this->node : Permission::class))->toBeFalse();
})->with([
    'restore' => ['restore', true],
    'forceDelete' => ['forceDelete', true],
    'deleteAny' => ['deleteAny', false],
    'restoreAny' => ['restoreAny', false],
    'forceDeleteAny' => ['forceDeleteAny', false],
]);

it('forbids deleting built-in nodes with a message, even to super admins', function (): void {
    $response = Gate::forUser(superAdminActor())->inspect('delete', Permission::factory()->builtin()->create());

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe('Built-in menu nodes cannot be deleted.');
});

it('lets operators update built-in nodes', function (): void {
    expect(Gate::forUser(userWithPermissions('system.menu.update'))->allows('update', Permission::factory()->builtin()->create()))->toBeTrue();
});

it('is the policy of the Permission model', function (): void {
    expect(Gate::getPolicyFor(Permission::class))->toBeInstanceOf(PermissionPolicy::class);
});
