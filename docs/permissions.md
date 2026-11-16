# 权限：功能权限与数据权限

Edge Admin 只使用 Laravel 原生的 Gate / Policy / `can:` 中间件，不依赖第三方权限包。权限码清单与接口见 [architecture.md §3](architecture.md#3-权限模型)。

## 1. 功能权限

### 1.1 声明与同步

权限点**写在代码里**：`server/config/permissions.php` 以树形声明（模块 → 菜单 → 操作），节点的 `actions` 展开为 `<节点 code>.<key>`：

```php
[
    'code' => 'content',
    'name' => '内容管理',
    'children' => [
        [
            'code' => 'content.article',
            'name' => '文章管理',
            'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
        ],
        // edge:permissions:content   ← 代码生成器的插入锚点
    ],
],
```

`php artisan permission:sync` 将其同步到 `permissions` 表（幂等：新增、更新名称 / 层级 / 排序，删除配置中已不存在的项）。
`DatabaseSeeder` 会自动执行；**每次部署也应执行一次**。界面上权限只读，仅用于给角色授权。

- 权限码格式 `模块.资源.操作`，**必须包含 `.`**；模块与菜单节点自身也是权限码（`type=menu`），用于前端菜单显示；
- `name` 为中文默认名称；前端按 `code` 做多语言展示，后端不翻译权限名称。

### 1.2 使用

```php
// 路由：官方 can 中间件
Route::get('system-info', SystemInfoController::class)->middleware('can:monitor.system-info.view');

Route::apiResource('articles', ArticleController::class)
    ->middlewareFor(['index', 'show'], 'can:content.article.view')
    ->middlewareFor('store', 'can:content.article.create')
    ->middlewareFor('update', 'can:content.article.update')
    ->middlewareFor('destroy', 'can:content.article.delete');

// 代码中
$user->can('content.article.update');
$user->hasPermission('content.article.update');
```

`AppServiceProvider` 中的 `Gate::before()`：ability 含 `.` 视为权限码，用户拥有即放行（超级管理员拥有全部）；
不含 `.` 的 ability（`view`、`update`、`delete`…）返回 `null` 交给 Policy，因此**超级管理员同样受记录级规则约束**（如不能删除自己）。

### 1.3 角色与用户

- 角色 `code = super-admin` 为超级管理员：拥有全部权限，不可删除 / 禁用 / 改码；`id = 1` 的用户受保护（不可删除 / 禁用 / 去掉超级管理员角色）；
- 被禁用的角色不生效；被禁用的用户无法登录，已签发的 token 被 `active` 中间件拒绝（401）并删除；
- `/api/auth/me` 的 `permissions` 返回用户实际拥有的权限码全集，前端据此控制菜单与按钮。

## 2. 记录级规则（Policy）

功能权限之外、与具体记录相关的规则写在 Policy，返回带 message 的 `Response::deny()`（403）：

```php
class DictTypePolicy
{
    public function delete(User $actor, DictType $dictType): Response
    {
        return $dictType->items()->exists()
            ? Response::deny(__('policy.dict_type.has_items'))
            : Response::allow();
    }
}
```

- 删除类：控制器中 `Gate::authorize('delete', $model)`；
- 编辑类：放在 Form Request 的 `authorize()` 中 `return Gate::inspect('update', $model);`，保证**先授权再校验**；
- 字段级约束（如 id=1 用户不可禁用）放在 Form Request，返回 422。

## 3. 数据权限

### 3.1 范围

角色字段 `data_scope`：`all`（全部）、`custom`（自定义部门，`department_role`）、`department_and_children`（本部门及下级）、
`department`（仅本部门）、`self`（仅本人创建）。多角色取**并集**，超级管理员恒为全部。解析逻辑在 `App\Services\DataScopeResolver`。

### 3.2 接入

```php
class Article extends Model
{
    use HasDataScope;   // 约定字段 department_id + created_by，可覆盖 dataScopeColumns()
}

// 列表
Article::query()->visibleTo($actor)->filter($request->filters());

// 单条（Policy）
public function update(User $actor, Article $article): bool
{
    return $article->isVisibleTo($actor);
}
```

- 创建记录时自动填充 `created_by`（当前用户）与 `department_id`（当前用户部门）；
- 列表与单条使用同一套规则，越权访问单条返回 403；
- 代码生成器的 `--data-scope` 会生成以上全部代码。

### 3.3 扩展

- **字段名不同**：在模型上覆盖 `dataScopeColumns()`，如用户表 `['department' => 'department_id', 'owner' => 'id']`；
- **新的范围类型**：在 `App\Enums\DataScope` 增加枚举值（含 `weight()`），并在 `DataScopeResolver::resolve()` 中计算可见部门 / 是否含本人；
- **非部门维度**（如按区域、门店）：参照 `HasDataScope` 新建 trait 与解析器，保持"列表 scope + 单条判定"两个入口一致。
