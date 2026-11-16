<?php

use App\Models\Collections\TreeCollection;
use App\Models\Department;

function node(int $id, ?int $parentId): Department
{
    return (new Department)->forceFill(['id' => $id, 'parent_id' => $parentId]);
}

test('flat nodes are assembled into a tree', function () {
    $tree = (new TreeCollection([node(1, null), node(2, 1), node(3, 2), node(4, null)]))->toTree();

    expect($tree->pluck('id')->all())->toBe([1, 4])
        ->and($tree->first()->children->pluck('id')->all())->toBe([2])
        ->and($tree->first()->children->first()->children->pluck('id')->all())->toBe([3]);
});

test('nodes whose parent is missing become roots', function () {
    $tree = (new TreeCollection([node(2, 1), node(3, 2)]))->toTree();

    expect($tree->pluck('id')->all())->toBe([2]);
});
