<?php

namespace App\Models\Collections;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * 以 parent_id 自关联的模型集合，可在 PHP 侧组装为树（与数据库无关）。
 *
 * @template TKey of array-key
 * @template TModel of Model
 *
 * @extends Collection<TKey, TModel>
 */
final class TreeCollection extends Collection
{
    /**
     * 组装为树：每个节点的 children 关系被设置为其子节点；
     * 父节点不在集合内的节点视为根节点（便于过滤后的结果仍能成树）。
     *
     * @return static<int, TModel>
     */
    public function toTree(string $parentKey = 'parent_id'): static
    {
        $ids = $this->modelKeys();
        $childrenByParent = [];
        $roots = [];

        foreach ($this->items as $node) {
            $parentId = $node->getAttribute($parentKey);

            if (in_array($parentId, $ids, true)) {
                $childrenByParent[$parentId][] = $node;
            } else {
                $roots[] = $node;
            }
        }

        foreach ($this->items as $node) {
            $node->setRelation('children', new self($childrenByParent[$node->getKey()] ?? []));
        }

        return new self($roots);
    }
}
