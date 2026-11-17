# 权限：功能权限与数据权限

Edge Admin 只使用 Laravel 原生的 Gate / Policy 与官方授权中间件（以控制器 Attribute `#[Authorize]` 声明），不依赖第三方权限包。权限码清单与接口见 [architecture.md §3](architecture.md#3-权限模型)。

## 1. 功能权限与动态菜单

权限点与侧边菜单是同一棵树（`permissions` 表），节点三种类型：

| 类型 | 用途 | 可放的下级 | 关键字段 |
|---|---|---|---|
| `directory` 目录 | 侧边菜单分组 | 目录、菜单 | 名称、图标、排序 |
| `menu` 菜单 | 一个页面（前端静态路由的 `path`）或外链（`is_external` + http(s) URL，新窗口打开） | 操作 | + 路由地址 / 外链 |
| `action` 操作（按钮） | 接口与按钮的权限点，code 必须含 `.` | 无 | 名称、权限码 |

节点的 `code` 就是权限码。目录 / 菜单的权限码（如 `system`、`system.user`）只控制菜单显示与授权树的勾选，接口鉴权用操作权限码。

### 1.1 内置菜单与同步

内置菜单**写在代码里**：`server/config/permissions.php` 以树形声明（目录 → 菜单 → 操作），节点的 `actions` 展开为 `<节点 code>.<key>`：

```php
[
    'code' => 'content',
    'name' => '内容管理',
    'type' => 'directory',          // 省略时：有 children 为 directory，否则为 menu
    'path' => '/content',
    'icon' => 'ReadOutlined',       // @ant-design/icons 图标名，前端只在一级菜单显示
    'children' => [
        [
            'code' => 'content.article',
            'name' => '文章管理',
            'path' => '/content/articles',   // 菜单必填，对应 web/config/routes.ts 中的静态路由
            'icon' => 'FileTextOutlined',
            // 'visible' => false,           // 默认 true；false 时不在菜单中显示
            // 'external' => true,           // 外链：path 写 http(s) URL
            'actions' => ['view' => '查看', 'create' => '新增', 'update' => '编辑', 'delete' => '删除'],
        ],
        // edge:permissions:content   ← 代码生成器的插入锚点
    ],
],
```

`php artisan permission:sync` 把配置同步到 `permissions` 表并标记为内置（`is_system`）。`DatabaseSeeder` 会自动执行；**每次部署也应执行一次**。同步规则：

| 情况 | 处理 |
|---|---|
| 配置中有、表中没有 | 按配置新增（名称、类型、路由、图标、显示、排序），上级按 code 查找（上级被管理员移动过时挂到它当前的位置） |
| 两边都有 | 只修正**结构性字段** `type` / `path` / `is_external`（与前端静态路由绑定）并标记 `is_system`；`name` / `icon` / `sort` / `is_visible` / `status` / `parent_id` / `remark` 以数据库为准，**管理员在界面上的修改不会被覆盖** |
| 表中有、配置中没有的自定义节点 | 不处理（sync 不再删除任何节点） |
| 曾经内置、已从配置移除 | 解除 `is_system`，转为自定义节点（保留角色授权，命令输出列出这些节点），不需要时由管理员在菜单管理中删除——比自动删除安全：不会静默收回角色已有的授权 |

内置节点在界面上不可删除，`code` / `type` 不可修改（422），`path` / `is_external` 只读（提交的值被忽略，以配置为准）。

- 权限码格式 `模块.资源.操作`；**操作必须包含 `.`**（不含 `.` 的 ability 视为 Policy 方法名），目录 / 菜单可以是单段；
- `name` 为中文默认名称；前端按 `code` 做多语言展示（`permission.<code>`），**名称被管理员改过时直接显示 `name`**，
  因此 `config/permissions.php` 的 `name` 要与前端 `locales/zh-CN/permission.ts`（或页面文案中的 `permission.<code>`）保持一致。

### 1.2 隐藏与停用

| | 菜单 | 权限 | 直接访问页面 |
|---|---|---|---|
| 隐藏（`is_visible = false`） | 不显示（隐藏目录时其下级也不显示） | 仍然有效 | 可以访问 |
| 停用（`status = disabled`） | 不显示 | 该节点**及全部后代**的权限码对所有人（含超级管理员）失效，不出现在 `/api/auth/me` 与授权树中 | 403 |

包含 `system.permission.view` / `.update` 的节点（菜单管理及其祖先目录）不能停用，避免把所有人锁在菜单管理之外。
停用节点的角色授权会保留，重新启用后立即恢复。权限判定每个请求只查询一次 `permissions` 表（`App\Services\PermissionRegistry`，节点变更由 `PermissionObserver` 清除）。

### 1.3 新增一个页面（完整流程）

1. **写页面与静态路由**：`web/src/pages/<模块>/<页面>/` + `web/config/routes.ts`（`access: 'routeFilter'` + `permission: '<code>.view'`）。
   页面组件是编译期确定的，菜单只能指向已有的静态路由（或外链）。
2. **声明菜单**（二选一）：
   - 内置：在 `config/permissions.php` 加菜单节点（`path` = 静态路由路径、`icon`、`actions`），执行 `php artisan permission:sync`；
     接口在控制器动作上用 `#[Authorize('<操作码>')]` 声明。随代码发布，适合业务模块（代码生成器即这样做）；
   - 自定义：在「系统管理 → 菜单管理」新建菜单（路由地址填静态路由路径）及其下的按钮。适合外链、调整菜单结构或复用已有页面。
3. **授权给角色**：在「角色管理」勾选目录 / 菜单（菜单显示）与操作（接口 / 按钮）。超级管理员自动拥有全部。

前端侧边菜单来自 `GET /api/auth/menus`，登录、切换账号、菜单管理 / 角色保存后即时刷新；名称、图标、顺序、显隐都在菜单管理里调整，不需要改前端代码。

### 1.4 使用

```php
// 路由文件只描述结构
Route::apiResources(['articles' => ArticleController::class]);
Route::get('system-info', SystemInfoController::class)->name('system-info');

// 功能权限声明在控制器动作上（官方 Illuminate\Routing\Attributes\Controllers\Authorize，等价于 can:<权限码> 中间件）
class ArticleController extends Controller
{
    #[Authorize('content.article.view')]
    public function index(ArticleIndexRequest $request): AnonymousResourceCollection { /* ... */ }

    #[Authorize('content.article.delete')]
    #[Authorize('delete', 'article')]          // 记录级：Policy::delete($user, 路由参数 article)
    public function destroy(Article $article): Response { /* ... */ }
}

class SystemInfoController extends Controller
{
    #[Authorize('monitor.system-info.view')]
    public function __invoke(SystemInfo $systemInfo): JsonResponse { /* ... */ }
}

// 代码中
$user->can('content.article.update');
$user->hasPermission('content.article.update');
```

`AppServiceProvider` 中的 `Gate::before()`：ability 含 `.` 视为权限码，用户拥有即放行（超级管理员拥有全部未停用的权限码）；
不含 `.` 的 ability（`view`、`update`、`delete`…）返回 `null` 交给 Policy，因此**超级管理员同样受记录级规则约束**（如不能删除自己）。

**可发现性**：`php artisan route:list -v --path=api` 的中间件列显示每条路由的 `Authorize:<权限码>`（Attribute 声明的中间件与路由上声明的完全一样地
出现在这里，路由缓存下同样生效）。`tests/Feature/RouteAuthorizationTest.php` 强制：`auth:sanctum` 下的每条路由**有且只有一个**功能权限
（登录即可访问的路由在测试的 `LOGIN_ONLY_ROUTES` 中显式列出），且用到的权限码都已在 `config/permissions.php` 中声明；
`tests/Arch/ConventionsTest.php` 禁止在 `routes/api.php` 中出现 `can:` / `middlewareFor()`。

### 1.5 角色与用户

- 角色 `code = super-admin` 为超级管理员：拥有全部（未停用的）权限，不可删除 / 禁用 / 改码；`id = 1` 的用户受保护（不可删除 / 禁用 / 去掉超级管理员角色）；
- 被禁用的角色不生效；被禁用的用户无法登录，已签发的 token 被 `active` 中间件拒绝（401）并删除；
- `/api/auth/me` 的 `permissions` 返回用户实际拥有且生效的权限码全集，前端据此控制路由与按钮；侧边菜单由 `/api/auth/menus` 下发。

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

- 在控制器动作上声明：`#[Authorize('delete', 'dict_type')]`（第二个参数是路由参数名，取到的是路由模型绑定后的模型）。
  它是官方 `Authorize` 中间件，排在 `SubstituteBindings` 之后、控制器解析 Form Request 之前执行，因此编辑接口天然**先授权再校验**，
  控制器与 Form Request 中都不需要 `Gate::authorize()` / `Gate::inspect()`（架构测试禁止控制器依赖 `Gate`）；
- 例外：Policy 需要请求体中的值时（如 `updateStatus` 需要提交的 `status`），在 Form Request 的 `authorize()` 中
  `return Gate::inspect('updateStatus', [$this->route('user'), $this->string('status')->toString()]);`；
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
