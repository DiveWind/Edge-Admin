<?php

use App\Core\System\Services\{DataScopeRegistry, SelfDataScope};

/*
|--------------------------------------------------------------------------
| GET /system/roles/data-scopes：数据范围选项（设计文档 §6.5、§8.4）
|--------------------------------------------------------------------------
*/

it('returns 401 for guests', function (): void {
    $this->getJson('/api/admin/system/roles/data-scopes')->assertUnauthorized();
});

it('returns 403 without the role create or update code', function (): void {
    syncBuiltinPermissions();

    $this->actingAs(userWithPermissions('system.role'))
        ->getJson('/api/admin/system/roles/data-scopes')
        ->assertForbidden();
});

it('lists the registered scopes with localized labels', function (string $locale, array $labels): void {
    syncBuiltinPermissions();

    $this->actingAs(userWithPermissions('system.role.update'))
        ->getJson('/api/admin/system/roles/data-scopes', ['Accept-Language' => $locale])
        ->assertOk()
        ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => [
            ['value' => 'all', 'label' => $labels[0]],
            ['value' => 'self', 'label' => $labels[1]],
        ]]);
})->with([
    'English' => ['en-US', ['All data', 'Own data only']],
    'Chinese' => ['zh-CN', ['全部数据', '仅本人数据']],
]);

it('includes the scopes registered by modules', function (): void {
    syncBuiltinPermissions();
    resolve(DataScopeRegistry::class)->register(new class extends SelfDataScope
    {
        public function key(): string
        {
            return 'department';
        }

        public function label(): string
        {
            return 'Department';
        }
    });

    $this->actingAs(superAdminActor())
        ->getJson('/api/admin/system/roles/data-scopes')
        ->assertOk()
        ->assertJsonPath('data.*.value', ['all', 'self', 'department']);
});
