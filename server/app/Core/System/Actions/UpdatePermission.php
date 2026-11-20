<?php

namespace App\Core\System\Actions;

use App\Core\System\Data\MenuNodeData;
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Permission, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 修改菜单与权限节点（PUT /system/menus/{menu}，设计文档 §6.5、§8.2、§8.6），以下违规都返回 422，错误挂在对应字段上：
 *
 * - 内置节点：name、type、path、component 与 meta.link、meta.iframeSrc 是结构性字段，只能由同步命令修改（§8.2）——
 *   提交的值与库中不同 → 422（name / type / path / component / meta.link / meta.iframeSrc）；
 *   title、icon、sort、status、parent_id 与其余 meta 键可以修改，再次同步不会覆盖；
 * - 上级不能是自己或自己的子孙（成环）→ 422（parent_id）；
 * - 类型层级（§8.2）：新的类型必须能挂在新的上级下 → 422（移动了上级时挂 parent_id，否则挂 type）；
 *   新的类型必须仍能容纳现有的子节点 → 422（type）；
 * - 生命线（§8.6）：system.menu、system.menu.update 及其（修改后的）全部祖先必须保持启用——停用它们 → 422（status），
 *   把它们移到停用的节点下 → 422（parent_id）；修改前就已失效的不拦（不是本次修改造成的）；
 * - 生效规则（§8.6）：非超管不能让任何当前不生效的节点（本身或某个祖先停用）变为生效——启用节点、把节点移出停用的祖先，
 *   都可能让仍保留在角色上的授权重新生效（停用不删授权），等于给自己恢复被收回的权限 → 422（启用挂 status，移动挂 parent_id）；
 *   超管不受限；
 * - 权限码、path 唯一 → 422（name / path，见 CreatePermission::save()）。
 *
 * 保存后 spatie 自动清除权限缓存，`/menu/all`、`/auth/codes` 随即按新数据计算。删除内置节点的 403 见 PermissionPolicy。
 */
class UpdatePermission
{
    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（CreatePermission::ATTRIBUTES；parent_id、status 未提交时保持不变）
     *
     * @throws ValidationException
     */
    public function handle(Permission $permission, array $attributes, User $actor): Permission
    {
        return DB::transaction(function () use ($permission, $attributes, $actor): Permission {
            $permission->fill(CreatePermission::only($attributes));

            // 内置节点的结构性字段错误优先（同一字段上不再给出层级等次要提示）
            $errors = [...$this->checkTree($permission, $actor), ...($permission->is_builtin ? $this->protectBuiltin($permission) : [])];

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            CreatePermission::save($permission);

            return $permission->refresh();
        });
    }

    /**
     * 内置节点的结构性字段不可修改（§8.6）。
     *
     * @return array<string, list<string>>
     */
    private function protectBuiltin(Permission $permission): array
    {
        $errors = [];

        $messages = [
            'name' => __('The permission code of a built-in node cannot be changed.'),
            'type' => __('The type of a built-in node cannot be changed.'),
            'path' => __('The path of a built-in node cannot be changed.'),
            'component' => __('The component of a built-in node cannot be changed.'),
        ];

        foreach ($messages as $field => $message) {
            if ($permission->isDirty($field)) {
                $errors[$field] = [$message];
            }
        }

        $original = $permission->getOriginal('meta');
        $original = is_array($original) ? $original : [];

        foreach (MenuNodeData::STRUCTURAL_META_KEYS as $key) {
            if (($original[$key] ?? null) !== ($permission->meta[$key] ?? null)) {
                $errors["meta.{$key}"] = [__('The URL of a built-in node cannot be changed.')];
            }
        }

        return $errors;
    }

    /**
     * 树结构的规则：成环、类型层级、容纳现有子节点、生命线、生效规则。全部节点一次查出（节点数量有限）。
     *
     * @return array<string, list<string>>
     */
    private function checkTree(Permission $permission, User $actor): array
    {
        $nodes = [];

        foreach (Permission::query()->get(['id', 'parent_id', 'type', 'name', 'status']) as $node) {
            $nodes[$node->id] = $node;
        }

        $moved = $permission->isDirty('parent_id');

        if ($moved && $permission->parent_id !== null && $this->isSelfOrDescendant($permission->parent_id, $permission->id, $nodes)) {
            return ['parent_id' => [__('A node cannot be placed under itself or its descendants.')]];
        }

        $errors = [];
        $parent = $permission->parent_id === null ? null : ($nodes[$permission->parent_id] ?? null);

        if (($moved || $permission->isDirty('type')) && ! $permission->type->canBeChildOf($parent?->type)) {
            $errors[$moved ? 'parent_id' : 'type'] = [CreatePermission::hierarchyMessage($permission->type)];
        }

        if ($permission->isDirty('type') && array_any($nodes, fn (Permission $node): bool => $node->parent_id === $permission->id && ! $permission->type->canContain($node->type))) {
            $errors['type'] = [__('The new type cannot contain the existing child nodes.')];
        }

        return [...$errors, ...$this->protectLifeline($permission, $nodes), ...($actor->isSuperAdmin() ? [] : $this->protectActivation($permission, $nodes))];
    }

    /**
     * 非超管不能让当前不生效的节点（本身或某个祖先停用）变为生效（§8.6）：比较修改前后每个节点的生效状态。
     *
     * @param  array<int, Permission>  $nodes  修改前的全部节点
     * @return array<string, list<string>>
     */
    private function protectActivation(Permission $permission, array $nodes): array
    {
        if (! $permission->isDirty(['status', 'parent_id'])) {
            return [];
        }

        $after = $nodes;
        $after[$permission->id] = $permission;

        foreach (array_keys($nodes) as $id) {
            if ($this->isBroken($this->chain($id, $nodes)) && ! $this->isBroken($this->chain($id, $after))) {
                $field = $permission->isDirty('status') && $permission->isEnabled() ? 'status' : 'parent_id';

                return [$field => [__('Only super admins can make disabled menu nodes take effect again.')]];
            }
        }

        return [];
    }

    /**
     * $id 是否为 $ancestorId 本身或其子孙（沿上级向上查找，遇到成环的脏数据时停下）。
     *
     * @param  array<int, Permission>  $nodes
     */
    private function isSelfOrDescendant(int $id, int $ancestorId, array $nodes): bool
    {
        $visited = [];

        for ($current = $id; $current !== null && ! isset($visited[$current]); $current = $nodes[$current]->parent_id ?? null) {
            if ($current === $ancestorId) {
                return true;
            }

            $visited[$current] = true;
        }

        return false;
    }

    /**
     * 生命线节点及其全部祖先在修改后必须仍然启用；只拦截由本次修改造成的失效。
     *
     * @param  array<int, Permission>  $nodes  修改前的全部节点
     * @return array<string, list<string>>
     */
    private function protectLifeline(Permission $permission, array $nodes): array
    {
        if (! $permission->isDirty(['status', 'parent_id'])) {
            return [];
        }

        $after = $nodes;
        $after[$permission->id] = $permission;

        foreach ($nodes as $node) {
            if (! in_array($node->name, Permission::LIFELINE_NAMES, true)) {
                continue;
            }

            $chain = $this->chain($node->id, $after);

            if (! $this->isBroken($chain) || $this->isBroken($this->chain($node->id, $nodes))) {
                continue;
            }

            return $permission->isDirty('status') && $permission->status === Status::Disabled && in_array($permission->id, array_keys($chain), true)
                ? ['status' => [__('The menu management nodes and their ancestors cannot be disabled.')]]
                : ['parent_id' => [__('The menu management nodes cannot be moved under a disabled node.')]];
        }

        return [];
    }

    /**
     * 节点及其全部祖先（id => 节点），遇到成环的脏数据时停下。
     *
     * @param  array<int, Permission>  $nodes
     * @return array<int, Permission>
     */
    private function chain(int $id, array $nodes): array
    {
        $chain = [];

        for ($current = $nodes[$id] ?? null; $current !== null && ! isset($chain[$current->id]); $current = $current->parent_id === null ? null : ($nodes[$current->parent_id] ?? null)) {
            $chain[$current->id] = $current;
        }

        return $chain;
    }

    /**
     * 链上是否有停用的节点。
     *
     * @param  array<int, Permission>  $chain
     */
    private function isBroken(array $chain): bool
    {
        return array_any($chain, fn (Permission $node): bool => ! $node->isEnabled());
    }
}
