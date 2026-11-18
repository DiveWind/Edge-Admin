<?php

use Tests\Core\Generator\TemporaryProject;

/*
|--------------------------------------------------------------------------
| core:make-contract：接口、只读数据对象、实现，以及模块 Provider 中的绑定
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->project = TemporaryProject::create($this->app);

    $this->artisan('core:make-module', ['name' => 'Device'])->assertSuccessful();
});

afterEach(function (): void {
    $this->project->delete();
});

it('creates the contract, its data object and implementation and binds them', function (): void {
    $this->artisan('core:make-contract', ['name' => 'DeviceConfig', '--module' => 'Device'])->assertSuccessful();

    expect($this->project->read('app/Modules/Device/Contracts/DeviceConfig.php'))
        ->toContain('interface DeviceConfig', 'use App\Modules\Device\Data\DeviceConfigData;', 'public function find(string $id): ?DeviceConfigData;')
        ->and($this->project->read('app/Modules/Device/Data/DeviceConfigData.php'))->toContain('final readonly class DeviceConfigData')
        ->and($this->project->read('app/Modules/Device/Services/DeviceConfigService.php'))
        ->toContain('namespace App\Modules\Device\Services;', 'class DeviceConfigService implements DeviceConfig', 'use App\Modules\Device\Contracts\DeviceConfig;')
        ->and($this->project->read('app/Modules/Device/DeviceServiceProvider.php'))->toContain(
            "use App\\Modules\\Device\\Contracts\\DeviceConfig;\nuse App\\Modules\\Device\\Services\\DeviceConfigService;",
            "     * @var array<class-string, class-string>\n     */\n    public array \$bindings = [\n        DeviceConfig::class => DeviceConfigService::class,\n    ];\n\n    /**\n     * Register any application services.",
        );
});

it('adds further bindings to the same property without duplicates', function (): void {
    $this->artisan('core:make-contract', ['name' => 'DeviceConfig', '--module' => 'Device'])->assertSuccessful();
    $this->artisan('core:make-contract', ['name' => 'DeviceStatus', '--module' => 'Device'])->assertSuccessful();
    $this->artisan('core:make-contract', ['name' => 'DeviceConfig', '--module' => 'Device', '--force' => true])->assertSuccessful();

    $provider = $this->project->read('app/Modules/Device/DeviceServiceProvider.php');

    expect($provider)->toContain(
        'use App\Modules\Device\Contracts\{DeviceConfig, DeviceStatus};',
        'use App\Modules\Device\Services\{DeviceConfigService, DeviceStatusService};',
        "        DeviceConfig::class => DeviceConfigService::class,\n        DeviceStatus::class => DeviceStatusService::class,\n    ];",
    )
        ->and(substr_count($provider, 'public array $bindings'))->toBe(1)
        ->and(substr_count($provider, 'DeviceConfig::class =>'))->toBe(1);
});

it('keeps an existing data object', function (): void {
    $this->project->put('app/Modules/Device/Data/DeviceConfigData.php', 'existing');

    $this->artisan('core:make-contract', ['name' => 'DeviceConfig', '--module' => 'Device'])
        ->expectsOutputToContain('已存在，跳过')
        ->assertSuccessful();

    expect($this->project->read('app/Modules/Device/Data/DeviceConfigData.php'))->toBe('existing');
});

it('refuses to rebind a contract to another implementation', function (): void {
    $this->artisan('core:make-contract', ['name' => 'DeviceConfig', '--module' => 'Device'])->assertSuccessful();
    $provider = $this->project->read('app/Modules/Device/DeviceServiceProvider.php');
    $this->project->put('app/Modules/Device/DeviceServiceProvider.php', str_replace('DeviceConfig::class => DeviceConfigService::class', 'DeviceConfig::class => DeviceStatusService::class', $provider));

    $this->artisan('core:make-contract', ['name' => 'DeviceConfig', '--module' => 'Device', '--force' => true])
        ->expectsOutputToContain('已绑定到')
        ->expectsOutputToContain('public array $bindings = [')
        ->assertFailed();
});
