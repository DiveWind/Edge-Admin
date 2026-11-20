<?php

use App\Core\System\Models\User;
use App\Core\System\Policies\UserPolicy;
use App\Core\System\Services\EffectivePermissions;
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| UserPolicy：每个方法要求的权限码、数据范围与业务保护（设计文档 §6.5、§8.3、§8.4、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->target = User::factory()->create();
});

/**
 * 方法 => [作用于记录, 允许它的权限码]。
 */
dataset('user abilities', [
    'viewAny' => ['viewAny', false, 'system.user'],
    'view' => ['view', true, 'system.user'],
    'create' => ['create', false, 'system.user.create'],
    'update' => ['update', true, 'system.user.update'],
    'delete' => ['delete', true, 'system.user.delete'],
    'changeStatus' => ['changeStatus', true, 'system.user.status'],
    'resetPassword' => ['resetPassword', true, 'system.user.password'],
]);

/**
 * 记录级的方法 => 权限码。
 */
dataset('record abilities', [
    'view' => ['view', 'system.user'],
    'update' => ['update', 'system.user.update'],
    'delete' => ['delete', 'system.user.delete'],
    'changeStatus' => ['changeStatus', 'system.user.status'],
    'resetPassword' => ['resetPassword', 'system.user.password'],
]);

it('allows each ability only with its permission code', function (string $ability, bool $onRecord, string $code): void {
    $others = array_filter(
        resolve(EffectivePermissions::class)->registeredCodes(),
        fn (string $other): bool => $other !== $code && ! str_starts_with($other, "{$code}."),
    );
    $arguments = $onRecord ? $this->target : User::class;

    expect(Gate::forUser(userWithDataScope('all', ...$others))->allows($ability, $arguments))->toBeFalse()
        ->and(Gate::forUser(userWithDataScope('all', $code))->allows($ability, $arguments))->toBeTrue();
})->with('user abilities');

it('allows the form options (role dropdown) with the create or update code', function (): void {
    $others = array_filter(
        resolve(EffectivePermissions::class)->registeredCodes(),
        fn (string $other): bool => ! in_array($other, ['system.user.create', 'system.user.update'], true),
    );

    expect(Gate::forUser(userWithPermissions(...$others))->allows('viewFormOptions', User::class))->toBeFalse()
        ->and(Gate::forUser(userWithPermissions('system.user.create'))->allows('viewFormOptions', User::class))->toBeTrue()
        ->and(Gate::forUser(userWithPermissions('system.user.update'))->allows('viewFormOptions', User::class))->toBeTrue();
});

it('denies records outside the data scope', function (string $ability, string $code): void {
    $actor = userWithPermissions($code);
    $own = User::factory()->create(['created_by' => $actor->id]);

    expect(Gate::forUser($actor)->allows($ability, $this->target))->toBeFalse()
        ->and(Gate::forUser($actor)->allows($ability, $own))->toBeTrue();
})->with('record abilities');

it('lets the self scope see and edit the operator', function (string $ability, string $code): void {
    $actor = userWithPermissions($code);

    expect(Gate::forUser($actor)->allows($ability, $actor))->toBeTrue();
})->with([
    'view' => ['view', 'system.user'],
    'update' => ['update', 'system.user.update'],
]);

it('forbids operating on yourself, even to super admins', function (string $ability, string $message): void {
    $actor = superAdminActor();
    $response = Gate::forUser($actor)->inspect($ability, $actor);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe($message);
})->with([
    'delete' => ['delete', 'You cannot delete yourself.'],
    'changeStatus' => ['changeStatus', 'You cannot disable yourself.'],
    'resetPassword' => ['resetPassword', 'Change your own password in your profile settings.'],
]);

it('lets only super admins manage super admin users', function (string $ability, string $code): void {
    $superAdmin = superAdminActor();
    $response = Gate::forUser(userWithDataScope('all', $code))->inspect($ability, $superAdmin);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe('Only super admins can manage super admin users.')
        ->and(Gate::forUser(superAdminActor())->allows($ability, $superAdmin))->toBeTrue();
})->with([
    'update' => ['update', 'system.user.update'],
    'delete' => ['delete', 'system.user.delete'],
    'changeStatus' => ['changeStatus', 'system.user.status'],
    'resetPassword' => ['resetPassword', 'system.user.password'],
]);

it('denies the abilities that users do not support, even to super admins', function (string $ability, bool $onRecord): void {
    expect(Gate::forUser(superAdminActor())->allows($ability, $onRecord ? $this->target : User::class))->toBeFalse();
})->with([
    'restore' => ['restore', true],
    'forceDelete' => ['forceDelete', true],
    'deleteAny' => ['deleteAny', false],
    'restoreAny' => ['restoreAny', false],
    'forceDeleteAny' => ['forceDeleteAny', false],
]);

it('is the policy of the User model', function (): void {
    expect(Gate::getPolicyFor(User::class))->toBeInstanceOf(UserPolicy::class);
});
