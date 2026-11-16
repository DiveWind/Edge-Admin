<?php

use App\Enums\Status;
use App\Models\Permission;
use App\Models\User;

beforeEach(function () {
    $this->artisan('permission:sync');
});

/**
 * 菜单树的 code（深度优先）。
 *
 * @param  list<array<string, mixed>>  $nodes
 * @return list<string>
 */
function menuCodes(array $nodes): array
{
    return collect($nodes)->flatMap(fn (array $node): array => [$node['code'], ...menuCodes($node['children'] ?? [])])->all();
}

test('menus only contain granted, enabled and visible directories and menus', function () {
    actingAsUser(userWithPermissions(['content', 'content.article', 'content.article.view', 'content.category', 'system', 'system.user.view']));

    $response = $this->getJson('/api/auth/menus')->assertOk();

    // system 目录没有已授权的子菜单（只有操作 system.user.view）而被剔除；操作节点不出现在菜单中
    expect(menuCodes($response->json('data')))->toBe(['content', 'content.category', 'content.article'])
        ->and($response->json('data.0'))->toHaveKeys(['id', 'parent_id', 'name', 'code', 'type', 'path', 'icon', 'is_external', 'sort', 'children'])
        ->and($response->json('data.0.children.1.path'))->toBe('/content/articles');
});

test('the super admin sees every visible menu in sort order', function () {
    actingAsUser(superAdmin());
    Permission::query()->where('code', 'content')->sole()->update(['sort' => 0]);
    Permission::query()->where('code', 'dashboard')->sole()->update(['sort' => 1]);

    $menus = $this->getJson('/api/auth/menus')->assertOk()->json('data');

    expect(array_column($menus, 'code'))->toBe(['content', 'dashboard', 'system', 'monitor', 'message'])
        ->and(menuCodes($menus))->not->toContain('dashboard.view');
});

test('hidden menus disappear from the menu but keep their permission', function () {
    $user = actingAsUser(userWithPermissions(['content', 'content.article', 'content.article.view']));
    Permission::query()->where('code', 'content.article')->sole()->update(['is_visible' => false]);

    expect($this->getJson('/api/auth/menus')->assertOk()->json('data'))->toBe([]);
    $this->getJson('/api/articles')->assertOk();
    expect($user->fresh()?->permissionCodes())->toContain('content.article.view');
});

test('children of a hidden directory are not promoted to the root', function () {
    actingAsUser(superAdmin());
    Permission::query()->where('code', 'content')->sole()->update(['is_visible' => false]);

    expect(menuCodes($this->getJson('/api/auth/menus')->json('data')))->not->toContain('content.article');
});

test('disabling a node disables the permissions of its whole subtree', function () {
    $user = userWithPermissions(['content', 'content.article', 'content.article.view', 'dashboard', 'dashboard.view']);
    Permission::query()->where('code', 'content')->sole()->update(['status' => Status::Disabled]);

    actingAsUser($user->fresh());

    $this->getJson('/api/articles')->assertForbidden();
    $this->getJson('/api/dashboard')->assertOk();
    $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.permissions', ['dashboard', 'dashboard.view']);
    expect(menuCodes($this->getJson('/api/auth/menus')->json('data')))->toBe(['dashboard']);
});

test('disabled nodes are excluded for the super admin and from the authorization tree', function () {
    $admin = superAdmin();
    Permission::query()->where('code', 'monitor')->sole()->update(['status' => Status::Disabled]);
    Permission::query()->where('code', 'system.user')->sole()->update(['is_visible' => false]);

    actingAsUser($admin->fresh());

    $codes = $this->getJson('/api/auth/me')->json('data.permissions');
    $tree = menuCodes($this->getJson('/api/options/permissions')->assertOk()->json('data'));

    expect($codes)->not->toContain('monitor.login-log.view')->toContain('system.user.view')
        ->and($tree)->not->toContain('monitor')->not->toContain('monitor.login-log.view')->toContain('system.user')
        ->and($this->getJson('/api/login-logs')->assertForbidden());
});

test('the menu endpoint only requires login', function () {
    actingAsUser(User::factory()->create());

    $this->getJson('/api/auth/menus')->assertOk()->assertExactJson(['data' => []]);
});

test('the permission registry queries the permissions table once per request', function () {
    actingAsUser(superAdmin());
    DB::enableQueryLog();

    $this->getJson('/api/auth/menus')->assertOk();

    expect(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "permissions"'))->count())->toBe(1);
});
