<?php

use App\Core\System\Actions\SyncPermissions;
use App\Core\System\Models\{Role, User};
use App\Core\System\Services\PermissionRegistry;
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

/*
|--------------------------------------------------------------------------
| 授权辅助（设计文档 §10.5）
|--------------------------------------------------------------------------
*/

/**
 * 同步各模块声明的内置节点（System 的菜单与按钮等）到 permissions 表，并确保 super-admin 角色存在（core:sync-permissions）。
 */
function syncBuiltinPermissions(): void
{
    resolve(SyncPermissions::class)->handle();
}

/**
 * 拥有给定权限码的用户：一个启用角色授予这些权限码及其全部祖先（节点需已存在，如 syncBuiltinPermissions()）。
 */
function userWithPermissions(string ...$codes): User
{
    $role = Role::factory()->create();
    $role->givePermissionTo(resolve(PermissionRegistry::class)->withAncestors($codes));

    return User::factory()->create()->assignRole($role);
}

/**
 * 拥有启用的 super-admin 角色的用户（角色不存在时创建）。
 */
function superAdminActor(): User
{
    $role = Role::query()->where('name', Role::SUPER_ADMIN)->first() ?? Role::factory()->superAdmin()->create();

    return User::factory()->create()->assignRole($role);
}
