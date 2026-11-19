<?php

use App\Core\System\Models\Setting;

/*
|--------------------------------------------------------------------------
| PUT /system/setting-groups/{setting_group}：按分组批量修改系统参数（设计文档 §6.7、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    declareSettings(demoSettings());
});

it('returns 401 for guests', function (): void {
    $this->putJson('/api/admin/system/setting-groups/demo', ['limit' => 10])->assertUnauthorized();
});

it('returns 403 without the system.setting.update code', function (): void {
    $this->actingAs(userWithPermissions('system.setting'))
        ->putJson('/api/admin/system/setting-groups/demo', ['limit' => 10])
        ->assertForbidden();
});

it('updates only the submitted settings and returns the group', function (): void {
    Setting::factory()->create(['key' => 'demo.name', 'group' => 'demo', 'value' => 'Acme']);

    $this->actingAs(userWithPermissions('system.setting.update'))
        ->putJson('/api/admin/system/setting-groups/demo', ['limit' => '10', 'enabled' => '0', 'options' => ['nested' => ['key' => true]], 'notice' => null])
        ->assertOk()
        ->assertJsonPath('data.group', 'demo')
        ->assertJsonPath('data.settings.*.value', ['Acme', '', 10, 0.5, false, ['nested' => ['key' => true]]])
        ->assertJsonPath('data.settings.*.is_customized', [true, false, true, false, true, true]);
});

it('reports the errors on the setting names and saves nothing', function (): void {
    $this->actingAs(superAdminActor())
        ->putJson('/api/admin/system/setting-groups/demo', ['name' => 'Acme', 'limit' => 0, 'enabled' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['limit', 'enabled'])
        ->assertJsonMissingValidationErrors(['name']);

    $this->assertDatabaseCount('settings', 0);
});

it('rejects settings that are not declared in the group', function (string $field): void {
    $this->actingAs(superAdminActor())
        ->putJson('/api/admin/system/setting-groups/demo', ['limit' => 10, $field => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with(['unknown name' => 'missing', 'full key' => 'demo.name']);

it('returns 404 for groups that are not declared', function (): void {
    $this->actingAs(superAdminActor())
        ->putJson('/api/admin/system/setting-groups/missing', ['limit' => 10])
        ->assertNotFound();
});
