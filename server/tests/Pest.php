<?php

use App\Core\System\Actions\SyncPermissions;
use App\Core\System\Contracts\SettingRegistry;
use App\Core\System\Models\{Role, User};
use App\Core\System\Services\{PermissionRegistry, SettingRegistryService};
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
 * 角色的数据范围为新建角色的默认值 `self`（仅本人数据）；需要其他范围时用 userWithDataScope()。
 */
function userWithPermissions(string ...$codes): User
{
    return userWithDataScope('self', ...$codes);
}

/**
 * 拥有给定权限码、且角色选用给定数据范围（§8.4，如 `all`、`self`）的用户。
 */
function userWithDataScope(string $scope, string ...$codes): User
{
    $role = Role::factory()->create(['data_scope' => $scope]);
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

/*
|--------------------------------------------------------------------------
| 系统参数（设计文档 §6.7）
|--------------------------------------------------------------------------
*/

/**
 * 用给定的分组声明替换容器中的 SettingRegistry（之后解析的 Settings、接口的路由绑定都使用它）。
 *
 * @param  array<string, mixed>  $groups
 */
function declareSettings(array $groups): SettingRegistryService
{
    $registry = new SettingRegistryService;
    $registry->register($groups);
    app()->instance(SettingRegistry::class, $registry);

    return $registry;
}

/**
 * 测试用的分组 demo：每种类型一个参数，另有一个默认值来自 config 的、一个公开的。
 *
 * @return array<string, mixed>
 */
function demoSettings(): array
{
    return ['demo' => [
        'title' => 'demo.title',
        'settings' => [
            'name' => ['type' => 'string', 'name' => 'demo.name', 'default' => 'Demo', 'public' => true],
            'notice' => ['type' => 'text', 'name' => 'demo.notice', 'default' => ''],
            'limit' => ['type' => 'int', 'name' => 'demo.limit', 'config' => 'core.auth.login_rate_limit', 'rules' => ['min:1', 'max:100']],
            'ratio' => ['type' => 'float', 'name' => 'demo.ratio', 'default' => 0.5],
            'enabled' => ['type' => 'bool', 'name' => 'demo.enabled', 'default' => false],
            'options' => ['type' => 'json', 'name' => 'demo.options', 'default' => ['a' => 1]],
        ],
    ]];
}
