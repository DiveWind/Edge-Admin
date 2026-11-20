<?php

use App\Core\System\Models\{Permission, Setting};

/*
|--------------------------------------------------------------------------
| core:sync：依次执行 core:sync-permissions、core:sync-dicts、core:sync-settings（设计文档 §11.4）
|--------------------------------------------------------------------------
*/

it('runs the three syncs in order and is idempotent', function (): void {
    $this->artisan('core:sync')
        ->expectsOutputToContain('core:sync-permissions')
        ->expectsOutputToContain('core:sync-dicts')
        ->expectsOutputToContain('core:sync-settings')
        ->assertSuccessful();

    expect(Permission::query()->where('name', 'system.setting.update')->exists())->toBeTrue()
        ->and(Setting::query()->pluck('key')->all())->toContain('auth.login_rate_limit');

    $this->artisan('core:sync')
        ->expectsOutputToContain('节点已是最新，没有变更')
        ->expectsOutputToContain('字典已是最新，没有变更')
        ->expectsOutputToContain('系统参数已是最新，没有变更')
        ->assertSuccessful();
});

it('stops with a non-zero exit code at the first failing sync', function (): void {
    // 自定义节点占用了 System 声明的 path：core:sync-permissions 失败
    Permission::factory()->create(['name' => 'custom', 'path' => '/system/user']);

    $this->artisan('core:sync')
        ->expectsOutputToContain('core:sync-permissions 失败（退出码 1），后面的同步没有执行。')
        ->doesntExpectOutputToContain('core:sync-dicts')
        ->assertFailed();

    expect(Setting::query()->count())->toBe(0);
});
