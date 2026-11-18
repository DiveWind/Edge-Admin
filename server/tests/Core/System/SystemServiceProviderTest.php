<?php

use App\Core\System\Enums\Status;
use App\Core\System\Models\{Permission, Role, User};
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| Gate::before：权限码由 PermissionRegistry 判定，其他 ability 交给 Policy（设计文档 §8.1）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $catalog = Permission::factory()->catalog()->create(['name' => 'demo', 'path' => '/demo']);
    Permission::factory()->menu()->childOf($catalog)->create(['name' => 'demo.page', 'path' => '/demo/page']);

    $this->role = Role::factory()->create();
    $this->role->givePermissionTo('demo');
    $this->user = User::factory()->create()->assignRole($this->role);
    $this->admin = User::factory()->create()->assignRole(Role::factory()->superAdmin()->create());
});

it('decides registered permission codes by the effective codes', function (): void {
    Gate::define('demo.page', fn (): bool => true);

    expect($this->user->can('demo'))->toBeTrue()
        ->and($this->user->can('demo.page'))->toBeFalse()
        ->and($this->admin->can('demo.page'))->toBeTrue();
});

it('denies disabled nodes to super admins as well', function (): void {
    Permission::query()->where('name', 'demo')->sole()->update(['status' => Status::Disabled]);

    expect($this->admin->can('demo'))->toBeFalse()
        ->and($this->admin->can('demo.page'))->toBeFalse();
});

it('leaves other abilities to the policies, for super admins too', function (): void {
    Gate::define('publish', fn (User $user): bool => $user->is($this->user));

    expect($this->user->can('publish'))->toBeTrue()
        ->and($this->admin->can('publish'))->toBeFalse()
        ->and($this->admin->can('unknown.code'))->toBeFalse();
});

it('does not register the spatie permission check on the gate', function (): void {
    $this->role->update(['status' => Status::Disabled]);

    expect($this->user->refresh()->can('demo'))->toBeFalse();
});
