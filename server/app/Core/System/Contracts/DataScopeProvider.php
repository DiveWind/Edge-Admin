<?php

namespace App\Core\System\Contracts;

use App\Core\System\Models\{Role, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;

/**
 * 数据范围提供者（Core 的扩展点，设计文档 §5.5、§8.4）：决定用户能看哪些数据。
 *
 * 每个角色选用一个范围（roles.data_scope 存 key()）。Core 默认注册 `all`（全部）与 `self`（仅本人，按 created_by）；
 * 业务模块实现本接口（如按部门、区域），在自己 Provider 的 boot() 中注册到 DataScopeRegistry，角色表单中自动出现：
 *
 * ```php
 * public function boot(DataScopeRegistry $scopes): void
 * {
 *     $scopes->register(new DepartmentDataScope);
 * }
 * ```
 *
 * 实现必须无状态（注册在单例中，常驻内存下跨请求共用，§10.3）；constrain() 与 contains() 是同一条规则的两种写法。
 * 多角色、超管与 all 的合并规则由模型 trait HasDataScope 负责，提供者只描述自己这一种范围。
 */
interface DataScopeProvider
{
    /**
     * 范围标识，存入 roles.data_scope（小写，如 `self`、`department`）。
     */
    public function key(): string;

    /**
     * 显示名（多语言），作为角色表单的选项（GET /system/roles/data-scopes）。
     */
    public function label(): string;

    /**
     * 给查询加条件：只保留 $user 按本范围可见的记录。条件加在 $query 上（调用方已把它包在独立的括号组中）。
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Collection<int, Role>  $roles  该用户选用本范围的启用角色（如“自定义部门”据此查角色与部门的关联）
     */
    public function constrain(Builder $query, User $user, Collection $roles): void;

    /**
     * 单条判定，与 constrain() 同一规则：$record 是否对 $user 可见。
     *
     * @param  Collection<int, Role>  $roles  该用户选用本范围的启用角色
     */
    public function contains(Model $record, User $user, Collection $roles): bool;
}
