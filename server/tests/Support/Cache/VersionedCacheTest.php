<?php

use App\Support\Cache\VersionedCache;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| VersionedCache：带版本号的整体缓存，避免未命中回填的竞态把旧值永久写回
|--------------------------------------------------------------------------
*/

it('loads once and reuses the value of the current version', function (): void {
    $cache = new VersionedCache('demo');
    $loads = 0;
    $load = function () use (&$loads): string {
        $loads++;

        return 'value';
    };

    expect($cache->remember($load))->toBe('value');
    app()->forgetScopedInstances();
    expect($cache->remember($load))->toBe('value')
        ->and($loads)->toBe(1);
});

it('reloads after a flush', function (): void {
    $cache = new VersionedCache('demo');
    $cache->remember(fn (): string => 'old');

    $cache->flush();

    expect($cache->remember(fn (): string => 'new'))->toBe('new');
});

it('does not serve a stale value written back after the flush by a reader that missed before it', function (): void {
    $cache = new VersionedCache('demo');

    // 读者 R：未命中，取到当前版本，读库得到旧值……
    $version = $cache->version();

    // ……写入方提交并 flush()……
    $cache->flush();
    app()->forgetScopedInstances();

    // ……R 之后才把旧值写回（写在旧版本的键下）
    Cache::put("demo:{$version}", 'stale', 60);

    expect($cache->remember(fn (): string => 'fresh'))->toBe('fresh');
});

it('starts a new version when the version key is evicted', function (): void {
    $cache = new VersionedCache('demo');
    $cache->remember(fn (): string => 'old');

    Cache::forget($cache->versionKey());
    app()->forgetScopedInstances();

    expect($cache->remember(fn (): string => 'new'))->toBe('new');
});
