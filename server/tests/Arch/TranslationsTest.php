<?php

use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\Lang;

require_once __DIR__.'/helpers.php';

/*
|--------------------------------------------------------------------------
| 语言包（设计文档 §6.1：提示语言按 Accept-Language 选择）
|--------------------------------------------------------------------------
|
| - config('core.locales') 中的语言都有语言包（en 使用框架自带英文，可只放覆盖项）；
| - lang/zh_CN/*.php 中的每个键都有英文版（lang/en 或框架自带英文）；
| - JSON 语言包（lang/zh_CN.json）以英文原文为键，英文直接使用键本身，无需单独翻译。
| - 两种键并存，按模块归属区分（设计文档 §4.4）：Core 用英文原文作为 JSON 键（或框架语言包的键，如 validation.exists），
|   业务模块只用自己的命名空间键 `<模块>::<文件>.<键>`（resources/lang，随模块一起删除），不写进根目录的 lang/。
|
*/

test('可选语言都有语言包', function (): void {
    foreach (config()->array('core.locales') as $locale) {
        expect(is_string($locale) && ($locale === 'en' || is_dir(archBasePath("lang/{$locale}"))))
            ->toBeTrue("lang/{$locale} 不存在");
    }
});

test('中文语言包中的键都有英文版', function (): void {
    $files = glob(archBasePath('lang/zh_CN/*.php')) ?: [];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $group = basename($file, '.php');
        $translations = require $file;

        foreach (array_keys(Arr::dot(is_array($translations) ? $translations : [])) as $key) {
            expect(Lang::has("{$group}.{$key}", 'en', false))->toBeTrue("缺少英文：{$group}.{$key}");
        }
    }
});

test('模块语言包（resources/lang）中，各语言的键一致', function (): void {
    $missing = [];

    foreach (glob(archBasePath('app/{Core,Modules}/*/resources/lang'), GLOB_BRACE | GLOB_ONLYDIR) ?: [] as $directory) {
        $keysByLocale = [];

        foreach (glob("{$directory}/*", GLOB_ONLYDIR) ?: [] as $localeDirectory) {
            foreach (glob("{$localeDirectory}/*.php") ?: [] as $file) {
                $translations = require $file;

                foreach (array_keys(Arr::dot(is_array($translations) ? $translations : [])) as $key) {
                    $keysByLocale[basename($localeDirectory)][] = basename($file, '.php').'.'.$key;
                }
            }
        }

        $all = array_unique(array_merge(...array_values($keysByLocale), ...[[]]));

        foreach ($keysByLocale as $locale => $keys) {
            foreach (array_diff($all, $keys) as $key) {
                $missing[] = str_replace(archBasePath().'/', '', $directory)."/{$locale}: {$key}";
            }
        }
    }

    expect($missing)->toBe([], '模块的每种语言都要有相同的键');
});

test('Core 用 JSON 原文键，业务模块用自己的命名空间键，且键都有中文翻译（设计文档 §4.4）', function (): void {
    $violations = [];
    $checked = 0;

    foreach (archPhpFiles(['app/Core', 'app/Modules']) as $file) {
        $path = str_replace(archBasePath().'/', '', $file->getRealPath() ?: $file->getPathname());

        if (str_starts_with($path, 'app/Core/Generator/')) {
            continue; // 生成器的模板与输出是命令行文案；生成到业务模块的代码由 composer test:generator-e2e 检查
        }

        preg_match('#^app/(?<kind>Core|Modules)/(?<module>[^/]+)/#', $path, $match);
        $module = ($match['kind'] ?? null) === 'Modules' ? Str::snake($match['module']) : null;
        // 唯一的例外：core:make-* --core 生成到内核模块的代码沿用生成器的命名空间键（`<内核模块>::<文件>.<键>`，设计文档 §4.4）
        $coreNamespace = ($match['kind'] ?? null) === 'Core' ? Str::snake($match['module']) : null;

        foreach (archStringArgumentCalls((string) file_get_contents($file->getPathname()), ['__', 'trans', 'trans_choice']) as $call) {
            $key = $call['argument'];
            $checked++;

            $namespaced = preg_match('/^(?<namespace>[a-z_]+)::/', $key, $namespace) === 1;
            $wrongKind = $module === null
                ? $namespaced && $namespace['namespace'] !== $coreNamespace
                : ! $namespaced || $namespace['namespace'] !== $module;

            if ($wrongKind) {
                $violations[] = "{$path}:{$call['line']} [{$key}]：".($module === null ? 'Core 用英文原文作为 JSON 键' : "业务模块用 {$module}:: 命名空间键");
            } elseif (! Lang::has($key, 'zh_CN', false)) {
                $violations[] = "{$path}:{$call['line']} [{$key}]：缺少中文翻译";
            }
        }
    }

    expect($violations)->toBe([])->and($checked)->toBeGreaterThan(0);
});
