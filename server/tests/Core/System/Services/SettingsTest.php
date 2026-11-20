<?php

use App\Core\System\Actions\UpdateSettings;
use App\Core\System\Models\Setting;
use App\Core\System\Services\Settings;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Settings：系统参数的读取（类型、默认值、缓存，设计文档 §6.7）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    declareSettings(demoSettings());
});

/**
 * 在库中保存参数 $key 改过的值（JSON 解码后的值）。
 */
function storeSetting(string $key, mixed $value): void
{
    Setting::factory()->create(['key' => $key, 'group' => strstr($key, '.', true), 'value' => $value]);
}

it('returns the declared defaults while the settings are not customized', function (): void {
    config(['core.auth.login_rate_limit' => 7]);
    $settings = resolve(Settings::class);

    expect($settings->string('demo.name'))->toBe('Demo')
        ->and($settings->string('demo.notice'))->toBe('')
        ->and($settings->integer('demo.limit'))->toBe(7)
        ->and($settings->float('demo.ratio'))->toBe(0.5)
        ->and($settings->boolean('demo.enabled'))->toBeFalse()
        ->and($settings->array('demo.options'))->toBe(['a' => 1]);
});

it('returns the customized values with their declared types', function (): void {
    storeSetting('demo.name', 'Acme');
    storeSetting('demo.limit', 20);
    storeSetting('demo.ratio', 2);
    storeSetting('demo.enabled', true);
    storeSetting('demo.options', ['b' => [1, 2]]);
    $settings = resolve(Settings::class);

    expect($settings->get('demo.name'))->toBe('Acme')
        ->and($settings->get('demo.limit'))->toBe(20)
        ->and($settings->get('demo.ratio'))->toBe(2.0)
        ->and($settings->get('demo.enabled'))->toBeTrue()
        ->and($settings->get('demo.options'))->toBe(['b' => [1, 2]]);
});

it('falls back to the default when the stored value no longer matches the declared type', function (): void {
    storeSetting('demo.limit', 'twenty');

    expect(resolve(Settings::class)->integer('demo.limit'))->toBe(5);
});

it('rejects undeclared keys and typed reads of another type', function (): void {
    $settings = resolve(Settings::class);

    expect(fn () => $settings->get('demo.missing'))->toThrow(InvalidArgumentException::class, '系统参数 demo.missing 未声明')
        ->and(fn () => $settings->integer('demo.name'))->toThrow(LogicException::class, '声明为 string 类型');
});

it('rejects a config default of another type', function (): void {
    config(['core.auth.login_rate_limit' => '5']);

    expect(fn () => resolve(Settings::class)->get('demo.limit'))->toThrow(LogicException::class, "config('core.auth.login_rate_limit') 不是 int 类型");
});

it('returns only the settings declared public, whatever the database says', function (): void {
    Setting::factory()->create(['key' => 'demo.notice', 'group' => 'demo', 'is_public' => true, 'value' => 'secret']);
    storeSetting('demo.name', 'Acme');

    expect(resolve(Settings::class)->public())->toBe(['demo.name' => 'Acme']);
});

it('caches the stored values until a setting is written', function (): void {
    storeSetting('demo.limit', 20);
    $settings = resolve(Settings::class);
    expect($settings->integer('demo.limit'))->toBe(20);

    // 绕过模型事件直接改库：缓存中仍是旧值
    DB::table('settings')->update(['value' => '30']);
    expect($settings->integer('demo.limit'))->toBe(20);

    resolve(UpdateSettings::class)->handle(['demo.limit' => '40']);
    expect($settings->integer('demo.limit'))->toBe(40);

    resolve(UpdateSettings::class)->handle(['demo.limit' => null]);
    expect($settings->integer('demo.limit'))->toBe(5)
        ->and(Setting::query()->where('key', 'demo.limit')->value('value'))->toBeNull();
});

it('does not keep a stale value that a concurrent reader writes back after the change', function (): void {
    storeSetting('demo.limit', 10);
    $stale = resolve(Settings::class)->integer('demo.limit');
    $version = Settings::cache()->version();

    // 另一个请求：未命中、已读到旧值；此时管理员修改并提交（afterCommit 中 flush），它之后才把旧值写回缓存
    resolve(UpdateSettings::class)->handle(['demo.limit' => 20]);
    cache()->put(Settings::CACHE_KEY.":{$version}", ['demo.limit' => $stale], 60);
    app()->forgetScopedInstances();

    expect(resolve(Settings::class)->integer('demo.limit'))->toBe(20);
});
