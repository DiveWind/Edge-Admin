<?php

use App\Core\System\Enums\Status;
use App\Core\System\Models\{Permission, Role, User};
use App\Core\System\Services\EffectivePermissions;

/*
|--------------------------------------------------------------------------
| EffectivePermissions：有效权限码与菜单树（设计文档 §6.2、§8.1）
|--------------------------------------------------------------------------
|
| 节点：目录 a（菜单 a.page，按钮 a.page.create）、目录 b（菜单 b.page）。
|
*/

beforeEach(function (): void {
    $this->a = Permission::factory()->catalog()->create(['name' => 'a', 'path' => '/a']);
    $this->page = Permission::factory()->menu()->childOf($this->a)->create(['name' => 'a.page', 'path' => '/a/page']);
    $this->create = Permission::factory()->button()->childOf($this->page)->create(['name' => 'a.page.create']);
    $this->b = Permission::factory()->catalog()->create(['name' => 'b', 'path' => '/b']);
    $this->bPage = Permission::factory()->menu()->childOf($this->b)->create(['name' => 'b.page', 'path' => '/b/page']);
});

/**
 * 拥有一个授予了 $codes 的角色的用户。
 *
 * @param  list<string>  $codes
 */
function userWithCodes(array $codes, Status $roleStatus = Status::Enabled): User
{
    $role = Role::factory()->create(['status' => $roleStatus]);
    $role->givePermissionTo($codes);

    return User::factory()->create()->assignRole($role);
}

function superAdmin(Status $roleStatus = Status::Enabled): User
{
    return User::factory()->create()->assignRole(Role::factory()->superAdmin()->create(['status' => $roleStatus]));
}

function codesFor(User $user): array
{
    return resolve(EffectivePermissions::class)->codesFor($user);
}

it('knows which abilities are registered permission codes', function (): void {
    $registry = resolve(EffectivePermissions::class);

    expect($registry->isRegistered('a'))->toBeTrue()
        ->and($registry->isRegistered('a.page.create'))->toBeTrue()
        ->and($registry->isRegistered('a.page.delete'))->toBeFalse()
        ->and($registry->isRegistered('update'))->toBeFalse();
});

it('gives users the granted codes whose ancestors are granted too', function (): void {
    expect(codesFor(userWithCodes(['a', 'a.page', 'a.page.create', 'b.page'])))->toBe(['a', 'a.page', 'a.page.create']);
});

it('unions the codes of all enabled roles', function (): void {
    $user = userWithCodes(['a', 'a.page']);
    $other = Role::factory()->create();
    $other->givePermissionTo('b', 'b.page');
    $user->assignRole($other);

    expect(codesFor($user->refresh()))->toBe(['a', 'a.page', 'b', 'b.page']);
});

it('ignores the codes of disabled roles', function (): void {
    expect(codesFor(userWithCodes(['a', 'a.page'], Status::Disabled)))->toBe([]);
});

it('gives users without roles no codes', function (): void {
    expect(codesFor(User::factory()->create()))->toBe([]);
});

it('gives super admins every enabled node', function (): void {
    expect(codesFor(superAdmin()))->toBe(['a', 'a.page', 'a.page.create', 'b', 'b.page']);
});

it('treats users with a disabled super-admin role as ordinary users', function (): void {
    $user = superAdmin(Status::Disabled);

    expect($user->isSuperAdmin())->toBeFalse()
        ->and(codesFor($user))->toBe([]);
});

it('disables a node and its descendants for everyone, super admins included', function (): void {
    $this->page->update(['status' => Status::Disabled]);
    $registry = resolve(EffectivePermissions::class);

    expect(codesFor(superAdmin()))->toBe(['a', 'b', 'b.page'])
        ->and(codesFor(userWithCodes(['a', 'a.page', 'a.page.create'])))->toBe(['a'])
        ->and($registry->isEnabled('a.page.create'))->toBeFalse()
        ->and($registry->enabledCodes())->toBe(['a', 'b', 'b.page']);
});

it('recalculates after the permissions change within the same request', function (): void {
    $user = userWithCodes(['a']);
    expect(codesFor($user))->toBe(['a']);

    $user->roles->first()?->givePermissionTo('a.page');

    expect(codesFor($user))->toBe(['a', 'a.page']);

    $this->a->update(['status' => Status::Disabled]);

    expect(codesFor($user))->toBe([]);
});

it('builds the menu tree from the visible route nodes only', function (): void {
    $empty = Permission::factory()->catalog()->create(['name' => 'empty', 'path' => '/empty']);
    Permission::factory()->menu()->childOf($empty)->disabled()->create();
    $this->b->update(['sort' => -1]);

    $tree = resolve(EffectivePermissions::class)->menuTreeFor(superAdmin());

    expect(array_map(fn (Permission $node): string => $node->name, $tree))->toBe(['b', 'a'])
        ->and($tree[1]->children->pluck('name')->all())->toBe(['a.page'])
        ->and($tree[1]->children[0]->children->all())->toBe([]);
});

it('lists every registered code, disabled ones included', function (): void {
    $this->b->update(['status' => Status::Disabled]);

    expect(resolve(EffectivePermissions::class)->registeredCodes())->toBe(['a', 'a.page', 'a.page.create', 'b', 'b.page']);
});

it('completes the ancestors of codes in node order', function (): void {
    expect(resolve(EffectivePermissions::class)->withAncestors(['b.page', 'a.page.create', 'a', 'unknown']))
        ->toBe(['a', 'a.page', 'a.page.create', 'b', 'b.page']);
});

it('builds the tree of enabled nodes with buttons and empty catalogs', function (): void {
    $this->bPage->update(['status' => Status::Disabled]);
    Permission::factory()->button()->childOf($this->bPage)->create(['name' => 'b.page.create']);

    $tree = resolve(EffectivePermissions::class)->enabledTree();

    expect(array_map(fn (Permission $node): string => $node->name, $tree))->toBe(['a', 'b'])
        ->and($tree[0]->children->pluck('name')->all())->toBe(['a.page'])
        ->and($tree[0]->children->first()?->children->pluck('name')->all())->toBe(['a.page.create'])
        ->and($tree[1]->children->all())->toBe([]);
});
