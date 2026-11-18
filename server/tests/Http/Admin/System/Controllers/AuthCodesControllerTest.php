<?php

use App\Core\System\Models\{Permission, Role, User};

/*
|--------------------------------------------------------------------------
| GET /auth/codes：当前用户的有效权限码（设计文档 §6.2、§8.1）
|--------------------------------------------------------------------------
*/

it('returns 401 for guests', function (): void {
    $this->getJson('/api/admin/auth/codes')
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);
});

it('returns the effective codes of the user', function (): void {
    $catalog = Permission::factory()->catalog()->create(['name' => 'demo', 'path' => '/demo']);
    $page = Permission::factory()->menu()->childOf($catalog)->create(['name' => 'demo.page', 'path' => '/demo/page']);
    Permission::factory()->button()->childOf($page)->create(['name' => 'demo.page.create']);
    Permission::factory()->button()->childOf($page)->create(['name' => 'demo.page.delete']);
    $role = Role::factory()->create();
    $role->givePermissionTo('demo', 'demo.page', 'demo.page.create');

    $this->actingAs(User::factory()->create()->assignRole($role))
        ->getJson('/api/admin/auth/codes')
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => ['demo', 'demo.page', 'demo.page.create'], 'message' => 'ok']);
});

it('returns an empty list for users without roles', function (): void {
    Permission::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin/auth/codes')
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => [], 'message' => 'ok']);
});
