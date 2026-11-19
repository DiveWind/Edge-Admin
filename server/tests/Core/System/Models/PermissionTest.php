<?php

use App\Core\System\Models\{Permission, Role};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Permission：权限码格式与表结构的约束（设计文档 §6.2、§8.2）
|--------------------------------------------------------------------------
*/

it('accepts lower-case dot-separated codes that start with a letter ', function (string $name, bool $valid): void {
    expect(Permission::isValidName($name))->toBe($valid);
})->with([
    'catalog' => ['system', true],
    'button' => ['system.user.create', true],
    'digits, dashes and underscores' => ['shop2.order-item.batch_export', true],
    'segment starting with a digit' => ['system.2fa', true],
    'upper-case' => ['System', false],
    'starting with a digit' => ['2fa', false],
    'empty segment' => ['system..user', false],
    'trailing dot' => ['system.', false],
    'space' => ['system user', false],
]);

it('removes the grants of a deleted node through the foreign key, even without model events', function (): void {
    $node = Permission::factory()->create();
    $role = Role::factory()->create();
    $role->givePermissionTo($node);

    DB::table('permissions')->where('id', $node->id)->delete();

    $this->assertDatabaseMissing('role_has_permissions', ['permission_id' => $node->id]);
});

it('keeps paths unique among route nodes, while buttons have none', function (): void {
    $parent = Permission::factory()->menu()->create(['path' => '/demo']);
    Permission::factory()->childOf($parent)->button()->count(2)->create();

    expect(fn () => Permission::factory()->create(['path' => '/demo']))->toThrow(UniqueConstraintViolationException::class);
});
