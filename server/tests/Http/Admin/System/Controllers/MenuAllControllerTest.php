<?php

use App\Core\System\Enums\Status;
use App\Core\System\Models\{Permission, Role, User};
use Database\Seeders\DatabaseSeeder;

/*
|--------------------------------------------------------------------------
| GET /menu/all：当前用户可见的菜单树，结构与 Vben 的路由一致（设计文档 §6.2）
|--------------------------------------------------------------------------
*/

function superAdminUser(): User
{
    return User::factory()->create()->assignRole(Role::factory()->superAdmin()->create());
}

it('returns 401 for guests', function (): void {
    $this->getJson('/api/admin/menu/all')
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);
});

it('returns the System menus to the seeded admin', function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->actingAs(User::query()->where('username', 'admin')->sole())
        ->getJson('/api/admin/menu/all')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'system')
        ->assertJsonPath('data.0.meta', ['title' => 'system.title', 'icon' => 'lucide:settings', 'order' => 9000])
        ->assertJsonPath('data.0.children.*.name', ['system.user', 'system.role', 'system.menu', 'system.dict', 'system.setting', 'system.attachment'])
        ->assertJsonPath('data.0.children.0.component', '/system/user/list')
        ->assertJsonMissingPath('data.0.component')
        ->assertJsonMissingPath('data.0.children.0.children');
});

it('outputs the Vben route structure of each node type', function (): void {
    $catalog = Permission::factory()->catalog()->create(['name' => 'demo', 'path' => '/demo', 'title' => 'demo.title', 'icon' => 'lucide:box', 'sort' => 1]);
    $page = Permission::factory()->menu('/demo/page/list')->childOf($catalog)->create([
        'name' => 'demo.page', 'path' => '/demo/page', 'title' => 'demo.page.title', 'sort' => 3,
        'meta' => ['keepAlive' => true, 'hideInMenu' => true, 'redirect' => '/ignored'],
    ]);
    Permission::factory()->button()->childOf($page)->create(['name' => 'demo.page.create']);
    Permission::factory()->embedded('https://example.com/embed')->childOf($catalog)->create(['name' => 'demo.embed', 'path' => '/demo/embed', 'title' => 'demo.embed.title', 'sort' => 2]);
    Permission::factory()->link('https://example.com')->childOf($catalog)->create(['name' => 'demo.link', 'path' => '/demo/link', 'title' => 'demo.link.title', 'sort' => 1]);

    $this->actingAs(superAdminUser())
        ->getJson('/api/admin/menu/all')
        ->assertOk()
        ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => [[
            'name' => 'demo',
            'path' => '/demo',
            'meta' => ['title' => 'demo.title', 'icon' => 'lucide:box', 'order' => 1],
            'children' => [
                ['name' => 'demo.link', 'path' => '/demo/link', 'component' => 'IFrameView', 'meta' => ['link' => 'https://example.com', 'title' => 'demo.link.title', 'order' => 1]],
                ['name' => 'demo.embed', 'path' => '/demo/embed', 'component' => 'IFrameView', 'meta' => ['iframeSrc' => 'https://example.com/embed', 'title' => 'demo.embed.title', 'order' => 2]],
                ['name' => 'demo.page', 'path' => '/demo/page', 'component' => '/demo/page/list', 'meta' => ['keepAlive' => true, 'hideInMenu' => true, 'title' => 'demo.page.title', 'order' => 3]],
            ],
        ]]]);
});

it('drops catalogs without visible children and nodes outside the effective codes', function (): void {
    $visible = Permission::factory()->catalog()->create(['name' => 'visible', 'path' => '/visible', 'sort' => 2]);
    Permission::factory()->menu()->childOf($visible)->create(['name' => 'visible.page', 'path' => '/visible/page']);
    Permission::factory()->menu()->childOf($visible)->create(['name' => 'visible.hidden', 'path' => '/visible/hidden']);
    $nested = Permission::factory()->catalog()->create(['name' => 'nested', 'path' => '/nested', 'sort' => 1]);
    $inner = Permission::factory()->catalog()->childOf($nested)->create(['name' => 'nested.inner', 'path' => '/nested/inner']);
    Permission::factory()->menu()->childOf($inner)->disabled()->create(['name' => 'nested.inner.page', 'path' => '/nested/inner/page']);
    $root = Permission::factory()->menu()->create(['name' => 'root', 'path' => '/root', 'sort' => 3, 'status' => Status::Enabled]);
    $role = Role::factory()->create();
    $role->givePermissionTo('visible', 'visible.page', 'nested', 'nested.inner', 'nested.inner.page', 'root');

    $this->actingAs(User::factory()->create()->assignRole($role))
        ->getJson('/api/admin/menu/all')
        ->assertOk()
        ->assertJsonPath('data.*.name', ['visible', 'root'])
        ->assertJsonPath('data.0.children.*.name', ['visible.page'])
        ->assertJsonPath('data.1.component', $root->component);
});
