<?php

use App\Enums\DataScope;
use App\Enums\PermissionType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Autoload
|--------------------------------------------------------------------------
|
| laravel/pint 以源码方式安装时，它的 composer.json 会把 vendor/laravel/pint/app 也注册到 `App\`
| 前缀下，导致架构测试（tests/Arch）扫描到 Pint 自己的类。测试中只保留本项目的 app/ 目录。
|
*/

(require __DIR__.'/../vendor/autoload.php')->setPsr4('App\\', [dirname(__DIR__).'/app']);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * 创建一个拥有指定权限码与数据权限的用户。
 *
 * @param  list<string>  $codes
 * @param  array<string, mixed>  $attributes
 */
function userWithPermissions(array $codes, DataScope $scope = DataScope::All, array $attributes = []): User
{
    $role = Role::factory()->scope($scope)->create();
    $role->permissions()->sync(array_map(
        fn (string $code): int => Permission::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'type' => PermissionType::Action],
        )->id,
        $codes,
    ));

    $user = User::factory()->create($attributes);
    $user->roles()->attach($role);

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function superAdmin(array $attributes = []): User
{
    return User::factory()->superAdmin()->create($attributes);
}

/**
 * 以指定用户身份（Sanctum token）发起请求。
 */
function actingAsUser(User $user): User
{
    Sanctum::actingAs($user);

    return $user;
}
