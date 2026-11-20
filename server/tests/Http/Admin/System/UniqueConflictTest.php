<?php

use App\Core\System\Models\{DictItem, DictType, Permission, Role, User};
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| 修改时并发撞上唯一索引 → 422（设计文档 §5.4，UniqueConstraint）
|--------------------------------------------------------------------------
|
| 表单的 Rule::unique 通过之后、Action 保存之前，另一个请求抢先保存了同一个值：在 Action 开启事务时插入冲突的记录来模拟
| （插在 UniqueConstraint 的保存点之外，回滚到保存点后仍在，多个唯一字段时能据此判断是哪一个冲突）。
| 修改与新增一样返回 422，错误挂在冲突的字段上（而不是 500）。
|
*/

/**
 * 请求中第一次开启事务时（表单校验已通过），以 $conflict 插入一条占用同一个值的记录（只插一次）。
 *
 * @param  Closure(): mixed  $conflict
 */
function insertConflictWhenWriting(Closure $conflict): void
{
    $inserted = false;

    Event::listen(TransactionBeginning::class, function () use (&$inserted, $conflict): void {
        if ($inserted) {
            return;
        }

        $inserted = true;
        $conflict();
    });
}

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->actingAs(superAdminActor());
});

it('returns 422 on username or email when updating a user', function (string $field, array $payload): void {
    $user = User::factory()->create(['username' => 'bob', 'email' => 'bob@example.com']);
    insertConflictWhenWriting(fn () => User::factory()->create(['username' => 'carol', 'email' => 'carol@example.com']));

    $this->putJson("/api/admin/system/users/{$user->id}", ['username' => 'bob', 'name' => 'Bob', 'email' => 'bob@example.com', ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'username' => ['username', ['username' => 'carol']],
    'email' => ['email', ['email' => 'carol@example.com']],
]);

it('returns 422 on name when updating a role', function (): void {
    $role = Role::factory()->create(['name' => 'editor']);
    insertConflictWhenWriting(fn () => Role::factory()->create(['name' => 'writer']));

    $this->putJson("/api/admin/system/roles/{$role->id}", ['name' => 'writer', 'title' => 'Writer'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('returns 422 on name or path when updating a menu node', function (string $field, array $payload): void {
    $node = Permission::factory()->create(['name' => 'demo', 'path' => '/demo']);
    insertConflictWhenWriting(fn () => Permission::factory()->create(['name' => 'taken', 'path' => '/taken']));

    $this->putJson("/api/admin/system/menus/{$node->id}", ['type' => 'catalog', 'name' => 'demo', 'path' => '/demo', 'meta' => ['title' => 'Demo'], ...$payload])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'name' => ['name', ['name' => 'taken']],
    'path' => ['path', ['path' => '/taken']],
]);

it('returns 422 on code when updating a dictionary type', function (): void {
    $type = DictType::factory()->create(['code' => 'demo']);
    insertConflictWhenWriting(fn () => DictType::factory()->create(['code' => 'taken']));

    $this->putJson("/api/admin/system/dict-types/{$type->id}", ['code' => 'taken', 'name' => 'Demo'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('returns 422 on value when updating a dictionary item', function (): void {
    $type = DictType::factory()->create();
    $item = DictItem::factory()->for($type, 'type')->create(['value' => 'a']);
    insertConflictWhenWriting(fn () => DictItem::factory()->for($type, 'type')->create(['value' => 'b']));

    $this->putJson("/api/admin/system/dict-items/{$item->id}", ['value' => 'b', 'label' => 'B'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});
