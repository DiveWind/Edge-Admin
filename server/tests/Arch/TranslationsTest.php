<?php

use Illuminate\Support\Arr;
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
