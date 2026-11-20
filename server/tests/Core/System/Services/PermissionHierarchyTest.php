<?php

use App\Core\System\Enums\{PermissionType, Status};
use App\Core\System\Models\Permission;
use App\Core\System\Services\PermissionHierarchy;

/*
|--------------------------------------------------------------------------
| 菜单树的纯计算（设计文档 §8.1、§8.2、§8.6）：遍历、防环、“节点及全部祖先”的规则只有这一份实现
|--------------------------------------------------------------------------
*/

/**
 * 不入库的节点。
 */
function hierarchyNode(int $id, ?int $parentId, Status $status = Status::Enabled, PermissionType $type = PermissionType::Catalog, int $sort = 0): Permission
{
    return new Permission()->forceFill(['id' => $id, 'parent_id' => $parentId, 'name' => "n{$id}", 'type' => $type, 'status' => $status, 'sort' => $sort]);
}

it('walks a node and its ancestors, and tells descendants apart', function (): void {
    $tree = PermissionHierarchy::from([hierarchyNode(1, null), hierarchyNode(2, 1), hierarchyNode(3, 2), hierarchyNode(4, null)]);

    expect(array_keys($tree->chain(3)))->toBe([3, 2, 1])
        ->and($tree->isSelfOrDescendant(3, 1))->toBeTrue()
        ->and($tree->isSelfOrDescendant(1, 1))->toBeTrue()
        ->and($tree->isSelfOrDescendant(1, 3))->toBeFalse()
        ->and($tree->isSelfOrDescendant(4, 1))->toBeFalse()
        ->and(array_keys($tree->withAncestors([3, 4])))->toEqualCanonicalizing([1, 2, 3, 4]);
});

it('takes a node as effective only when it and every ancestor are enabled', function (): void {
    $tree = PermissionHierarchy::from([hierarchyNode(1, null), hierarchyNode(2, 1, Status::Disabled), hierarchyNode(3, 2), hierarchyNode(4, 1)]);

    expect($tree->isEffective(1))->toBeTrue()
        ->and($tree->isEffective(2))->toBeFalse()
        ->and($tree->isEffective(3))->toBeFalse()
        ->and($tree->isEffective(4))->toBeTrue()
        ->and(array_keys($tree->effectiveNodes()))->toBe([1, 4])
        ->and($tree->with(hierarchyNode(2, 1))->isEffective(3))->toBeTrue()
        ->and($tree->isEffective(3))->toBeFalse();
});

it('fails closed on dirty data: cycles and missing parents never take effect, and walking them terminates', function (): void {
    $tree = PermissionHierarchy::from([hierarchyNode(1, 2), hierarchyNode(2, 1), hierarchyNode(3, 99)]);

    expect(array_keys($tree->chain(1)))->toBe([1, 2])
        ->and($tree->isEffective(1))->toBeFalse()
        ->and($tree->isEffective(3))->toBeFalse()
        ->and($tree->isEffective(42))->toBeFalse()
        ->and($tree->isSelfOrDescendant(1, 3))->toBeFalse();
});

it('assembles a sorted tree from the root, dropping nodes whose parent is not visible, and optionally empty catalogs', function (): void {
    $nodes = [
        hierarchyNode(1, null, sort: 2),
        hierarchyNode(2, null, sort: 1),
        hierarchyNode(3, 1, type: PermissionType::Menu),
        hierarchyNode(4, 99),
    ];

    $tree = PermissionHierarchy::tree($nodes);

    expect(array_map(fn (Permission $node): int => $node->id, $tree))->toBe([2, 1])
        ->and($tree[1]->children->pluck('id')->all())->toBe([3])
        ->and($tree[1]->children->first()?->children->all())->toBe([])
        ->and($nodes[0]->relationLoaded('children'))->toBeFalse()
        ->and(array_map(fn (Permission $node): int => $node->id, PermissionHierarchy::tree($nodes, pruneEmptyCatalogs: true)))->toBe([1]);
});
