<?php

use App\Enums\SettingType;
use App\Models\LoginLog;
use App\Models\Setting;

test('setting values are validated and cast by type', function () {
    actingAsUser(userWithPermissions(['system.setting.create', 'system.setting.view']));
    $base = ['group' => 'custom', 'name' => '参数'];

    $this->postJson('/api/settings', [...$base, 'key' => 'custom.limit', 'type' => 'number', 'value' => '12'])
        ->assertCreated()
        ->assertJsonPath('data.value', 12);
    $this->postJson('/api/settings', [...$base, 'key' => 'custom.enabled', 'type' => 'boolean', 'value' => true])
        ->assertCreated()
        ->assertJsonPath('data.value', true);
    $this->postJson('/api/settings', [...$base, 'key' => 'custom.links', 'type' => 'json', 'value' => '{"a":1}'])
        ->assertCreated()
        ->assertJsonPath('data.value', ['a' => 1]);
    $this->postJson('/api/settings', [...$base, 'key' => 'custom.list', 'type' => 'json', 'value' => ['x', 'y']])
        ->assertCreated()
        ->assertJsonPath('data.value', ['x', 'y']);

    $this->postJson('/api/settings', [...$base, 'key' => 'custom.bad_number', 'type' => 'number', 'value' => 'abc'])
        ->assertUnprocessable()->assertJsonValidationErrors('value');
    $this->postJson('/api/settings', [...$base, 'key' => 'custom.bad_json', 'type' => 'json', 'value' => '{oops'])
        ->assertUnprocessable()->assertJsonValidationErrors('value');
    $this->postJson('/api/settings', [...$base, 'key' => 'Bad Key', 'type' => 'string', 'value' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('key');
    $this->postJson('/api/settings', [...$base, 'key' => 'custom.limit', 'type' => 'string', 'value' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('key');

    $this->getJson('/api/settings?key=custom.li')->assertOk()->assertJsonCount(3, 'data');
});

test('built-in settings cannot be deleted or have their key or type changed', function () {
    actingAsUser(superAdmin());
    $setting = Setting::factory()->system()->ofType(SettingType::Number, 5)->create(['key' => 'security.login_max_attempts']);
    $payload = ['group' => 'security', 'key' => 'security.login_max_attempts', 'name' => '登录尝试次数', 'type' => 'number', 'value' => 10];

    $this->putJson("/api/settings/{$setting->id}", [...$payload, 'key' => 'security.renamed'])
        ->assertUnprocessable()->assertJsonValidationErrors('key');
    $this->putJson("/api/settings/{$setting->id}", [...$payload, 'type' => 'string'])
        ->assertUnprocessable()->assertJsonValidationErrors('type');
    $this->putJson("/api/settings/{$setting->id}", $payload)
        ->assertOk()
        ->assertJsonPath('data.value', 10)
        ->assertJsonPath('data.is_system', true);

    $this->deleteJson("/api/settings/{$setting->id}")->assertForbidden()->assertJsonPath('message', '内置参数不可删除。');
    $this->deleteJson('/api/settings/'.Setting::factory()->create()->id)->assertNoContent();
});

test('settings management requires the setting permissions', function () {
    actingAsUser(userWithPermissions(['system.setting.view']));
    $setting = Setting::factory()->create();

    $this->getJson('/api/settings')->assertOk();
    $this->putJson("/api/settings/{$setting->id}", ['group' => 'g', 'key' => 'k', 'name' => 'n', 'type' => 'string'])->assertForbidden();
    $this->deleteJson("/api/settings/{$setting->id}")->assertForbidden();
});

test('public settings are available without authentication and reflect changes', function () {
    Setting::factory()->public()->create(['key' => 'site.name', 'value' => 'Edge Admin']);
    Setting::factory()->create(['key' => 'secret.internal', 'value' => 'hidden']);

    $this->getJson('/api/options/settings')
        ->assertOk()
        ->assertExactJson(['data' => ['site.name' => 'Edge Admin']]);

    Setting::query()->where('key', 'site.name')->firstOrFail()->update(['value' => '新名称']);

    $this->getJson('/api/options/settings')->assertJsonPath('data', ['site.name' => '新名称']);
    expect(setting('site.name'))->toBe('新名称')
        ->and(setting('missing.key', 'fallback'))->toBe('fallback');
});

test('the login rate limit follows the security.login_max_attempts setting', function () {
    Setting::factory()->system()->ofType(SettingType::Number, 2)->create(['key' => 'security.login_max_attempts']);

    $this->postJson('/api/auth/login', ['username' => 'nobody', 'password' => 'wrong'])->assertUnprocessable();
    $this->postJson('/api/auth/login', ['username' => 'nobody', 'password' => 'wrong'])->assertUnprocessable();
    $this->postJson('/api/auth/login', ['username' => 'nobody', 'password' => 'wrong'])->assertTooManyRequests();
});

test('log pruning follows the log.retention_days setting', function () {
    Setting::factory()->system()->ofType(SettingType::Number, 3)->create(['key' => 'log.retention_days']);
    LoginLog::factory()->create(['created_at' => now()->subDays(5)]);
    $recent = LoginLog::factory()->create(['created_at' => now()->subDays(2)]);

    $this->artisan('model:prune', ['--model' => [LoginLog::class]])->assertSuccessful();

    expect(LoginLog::query()->pluck('id')->all())->toBe([$recent->id]);
});
