<?php

use App\Core\System\Models\Setting;

/*
|--------------------------------------------------------------------------
| GET /settings/public：免登录的公开参数（设计文档 §6.7）
|--------------------------------------------------------------------------
|
| 只输出声明为 public 的参数：非公开参数在任何情况下都不能出现在这里。
|
*/

it('returns the public System settings to guests without the private ones', function (): void {
    config(['app.name' => 'Acme Admin']);

    $this->getJson('/api/admin/settings/public')
        ->assertOk()
        ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['site.name' => 'Acme Admin', 'site.login_notice' => '']])
        ->assertHeader('Cache-Control', 'no-cache, private');
});

it('never returns settings that are not declared public, whatever the database says', function (): void {
    declareSettings(demoSettings());
    Setting::factory()->create(['key' => 'demo.name', 'group' => 'demo', 'value' => 'Acme']);
    Setting::factory()->create(['key' => 'demo.limit', 'group' => 'demo', 'value' => 42, 'is_public' => true]);
    Setting::factory()->create(['key' => 'demo.released', 'group' => 'demo', 'value' => 'old secret', 'is_public' => true, 'is_builtin' => false]);

    $response = $this->getJson('/api/admin/settings/public')
        ->assertOk()
        ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['demo.name' => 'Acme']]);

    expect($response->getContent())->not->toContain('42')->not->toContain('old secret');
});

it('returns an empty array when nothing is public', function (): void {
    $groups = demoSettings();
    unset($groups['demo']['settings']['name']);
    declareSettings($groups);

    $this->getJson('/api/admin/settings/public')->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => []]);
});
