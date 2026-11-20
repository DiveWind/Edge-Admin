<?php

use App\Core\System\Actions\{CreatePermission, UpdatePermission};
use App\Core\System\Enums\PermissionType;
use App\Core\System\Models\Permission;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| 菜单管理的 Action（设计文档 §8.6）：接口无法直接触发的分支
|--------------------------------------------------------------------------
|
| 权限码与 path 的唯一性由表单先校验；两个请求同时保存同一个值时，后到的撞上数据库的唯一索引，Action 同样返回 422。
|
*/

beforeEach(function (): void {
    $this->taken = Permission::factory()->create(['name' => 'taken', 'path' => '/taken']);
});

it('returns 422 on the unique field when a concurrent request saved it first', function (string $field, array $attributes): void {
    expect(fn () => resolve(CreatePermission::class)->handle([
        ...['type' => PermissionType::Catalog, 'name' => 'demo', 'path' => '/demo', 'title' => 'Demo'],
        ...$attributes,
    ]))->toThrow(function (ValidationException $e) use ($field): void {
        expect(array_keys($e->errors()))->toBe([$field]);
    });

    $this->assertDatabaseMissing('permissions', ['title' => 'Demo']);
})->with([
    'name' => ['name', ['name' => 'taken']],
    'path' => ['path', ['path' => '/taken']],
]);

it('reports the path when updating a node to a taken path', function (): void {
    $node = Permission::factory()->create();

    expect(fn () => resolve(UpdatePermission::class)->handle($node, ['path' => '/taken'], superAdminActor()))
        ->toThrow(function (ValidationException $e): void {
            expect(array_keys($e->errors()))->toBe(['path']);
        });
});

it('returns 422 when the parent node no longer exists, instead of hitting the foreign key', function (): void {
    expect(fn () => resolve(CreatePermission::class)->handle(['type' => 'catalog', 'name' => 'orphan', 'path' => '/orphan', 'title' => 'orphan', 'parent_id' => 999_999]))
        ->toThrow(function (ValidationException $e): void {
            expect(array_keys($e->errors()))->toBe(['parent_id']);
        });
});
