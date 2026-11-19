<?php

use App\Core\System\Enums\SettingType;
use App\Core\System\Models\Setting;
use App\Core\System\Services\Settings;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| core:sync-settings：同步声明的系统参数（设计文档 §6.7）
|--------------------------------------------------------------------------
*/

function setting(string $key): Setting
{
    return Setting::query()->where('key', $key)->sole();
}

it('creates the declared settings without values', function (): void {
    declareSettings(demoSettings());

    $this->artisan('core:sync-settings')
        ->expectsOutputToContain('新增 6 个，修正 0 个，收编 0 个，转为自定义 0 个')
        ->assertSuccessful();

    expect(setting('demo.name'))
        ->group->toBe('demo')
        ->type->toBe(SettingType::String)
        ->name->toBe('demo.name')
        ->remark->toBeNull()
        ->is_public->toBeTrue()
        ->is_builtin->toBeTrue()
        ->and(setting('demo.name')->value)->toBeNull()
        ->and(setting('demo.limit'))->type->toBe(SettingType::Integer)->is_public->toBeFalse();
});

it('syncs the settings declared by the System module', function (): void {
    $this->artisan('core:sync-settings')->assertSuccessful();

    expect(Setting::query()->orderBy('id')->pluck('key')->all())->toBe(['site.name', 'site.login_notice', 'auth.login_rate_limit']);
});

it('is idempotent', function (): void {
    declareSettings(demoSettings());
    $this->artisan('core:sync-settings')->assertSuccessful();
    $before = Setting::query()->orderBy('id')->get()->toArray();

    $this->artisan('core:sync-settings')
        ->expectsOutputToContain('系统参数已是最新，没有变更')
        ->assertSuccessful();

    expect(Setting::query()->orderBy('id')->get()->toArray())->toBe($before);
});

it('keeps the values changed by administrators and fixes the structural fields', function (): void {
    declareSettings(demoSettings());
    $this->artisan('core:sync-settings')->assertSuccessful();
    setting('demo.limit')->forceFill(['value' => 20, 'name' => 'Changed', 'group' => 'other', 'is_public' => true])->save();

    $this->artisan('core:sync-settings')
        ->expectsOutputToContain('新增 0 个，修正 1 个，收编 0 个，转为自定义 0 个')
        ->assertSuccessful();

    expect(setting('demo.limit'))->name->toBe('demo.limit')->group->toBe('demo')->is_public->toBeFalse()
        ->and(setting('demo.limit')->value)->toBe(20);
});

it('resets values that no longer match a changed type', function (): void {
    declareSettings(demoSettings());
    $this->artisan('core:sync-settings')->assertSuccessful();
    setting('demo.name')->forceFill(['value' => 'Acme'])->save();
    setting('demo.notice')->forceFill(['value' => 'Hello'])->save();

    $groups = demoSettings();
    $groups['demo']['settings']['name'] = ['type' => 'int', 'name' => 'demo.name', 'default' => 1];
    $groups['demo']['settings']['notice'] = ['type' => 'string', 'name' => 'demo.notice', 'default' => ''];
    declareSettings($groups);

    $this->artisan('core:sync-settings')
        ->expectsOutputToContain('库中的值不再符合，已恢复默认值')
        ->assertSuccessful();

    expect(setting('demo.name'))->type->toBe(SettingType::Integer)
        ->and(setting('demo.name')->value)->toBeNull()
        ->and(setting('demo.notice'))->type->toBe(SettingType::String)
        ->and(setting('demo.notice')->value)->toBe('Hello');
});

it('turns removed declarations into custom settings, keeping their values, and adopts them again', function (): void {
    declareSettings(demoSettings());
    $this->artisan('core:sync-settings')->assertSuccessful();
    setting('demo.name')->forceFill(['value' => 'Acme'])->save();

    $groups = demoSettings();
    unset($groups['demo']['settings']['name']);
    declareSettings($groups);

    $this->artisan('core:sync-settings')
        ->expectsOutputToContain('转为自定义 1 个')
        ->expectsOutputToContain('demo.name')
        ->assertSuccessful();

    expect(setting('demo.name'))->is_builtin->toBeFalse()->is_public->toBeFalse()
        ->and(setting('demo.name')->value)->toBe('Acme');

    declareSettings(demoSettings());

    $this->artisan('core:sync-settings')->expectsOutputToContain('收编 1 个')->assertSuccessful();

    expect(setting('demo.name'))->is_builtin->toBeTrue()->is_public->toBeTrue()
        ->and(setting('demo.name')->value)->toBe('Acme');
});

it('clears the settings cache', function (): void {
    declareSettings(demoSettings());
    $this->artisan('core:sync-settings')->assertSuccessful();
    expect(resolve(Settings::class)->string('demo.name'))->toBe('Demo');

    DB::table('settings')->where('key', 'demo.name')->update(['value' => '"Acme"']);
    $this->artisan('core:sync-settings')->assertSuccessful();

    expect(resolve(Settings::class)->string('demo.name'))->toBe('Acme');
});
