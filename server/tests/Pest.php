<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Autoload
|--------------------------------------------------------------------------
|
| laravel/pint 以源码方式安装时（GitHub 的 zip 下载不可用、composer 退回 git 克隆），它的 composer.json 会把
| vendor/laravel/pint/app 也注册到 `App\` 前缀下，架构测试（tests/Arch）会扫描到 Pint 自己的类。
| 测试中只保留本项目的 app/ 目录；以 dist 方式安装（CI）时这一行没有影响。
|
*/

(require __DIR__.'/../vendor/autoload.php')->setPsr4('App\\', [__DIR__.'/../app']);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| - LazilyRefreshDatabase：只在测试第一次访问数据库时迁移（sqlite 内存库，见 phpunit.xml）；
| - Http::preventStrayRequests()：未 fake 的出站 HTTP 请求一律视为错误。
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Arch', 'Core', 'Http', 'Modules', 'Providers', 'Support');
