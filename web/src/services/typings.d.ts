/**
 * 跨页面共享的接口类型（全局命名空间，无需 import）。
 * 字段与 docs/architecture.md 契约保持一致：snake_case，时间为 ISO 8601 字符串。
 * 只属于某个页面的类型放在该页面目录的 data.d.ts 中。
 */
declare namespace API {
  type Id = number;

  /** 通用启停状态 */
  type Status = 'active' | 'disabled';

  /** 发布状态（文章、公告） */
  type PublishStatus = 'draft' | 'published';

  /** 角色数据权限范围 */
  type DataScope =
    | 'all'
    | 'custom'
    | 'department_and_children'
    | 'department'
    | 'self';

  /** 单条资源：`{ data: {...} }` */
  interface Resource<T> {
    data: T;
  }

  /** 非分页列表（树、options）：`{ data: [...] }` */
  interface Collection<T> {
    data: T[];
  }

  interface PaginationMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
  }

  interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  }

  /** Laravel 分页资源集合 */
  interface Paginated<T> {
    data: T[];
    links: PaginationLinks;
    meta: PaginationMeta;
  }

  /** 列表查询参数：分页 + 排序 + 过滤字段 */
  interface ListQuery {
    page?: number;
    per_page?: number;
    /** 如 `-created_at`、`sort`，仅后端白名单字段有效 */
    sort?: string;
    [filter: string]: string | number | boolean | undefined;
  }

  /** Laravel 失败响应 */
  interface ErrorBody {
    message: string;
    errors?: Record<string, string[]>;
  }

  /** 关联对象的简要形式，如 `department: {id, name}` */
  interface Brief {
    id: Id;
    name: string;
  }

  /** 带编码的简要形式（角色、岗位） */
  interface CodedBrief extends Brief {
    code: string;
  }

  /** `/api/options/*` 下拉项 */
  interface Option extends Brief {
    code?: string;
  }

  /** `/api/options/departments` 树节点 */
  interface DepartmentOption extends Brief {
    parent_id: Id | null;
    children?: DepartmentOption[];
  }

  /** 菜单树节点类型：目录 / 菜单 / 操作（按钮） */
  type PermissionType = 'directory' | 'menu' | 'action';

  /** `GET /api/permissions`、`GET /api/options/permissions` 树节点（菜单管理 / 角色授权） */
  interface PermissionNode {
    id: Id;
    parent_id: Id | null;
    name: string;
    code: string;
    type: PermissionType;
    /** 前端路由路径或外链 URL（action 为 null） */
    path: string | null;
    /** @ant-design/icons 图标名，如 `SettingOutlined` */
    icon: string | null;
    is_external: boolean;
    is_visible: boolean;
    status: Status;
    is_system: boolean;
    sort: number;
    remark: string | null;
    created_at?: string;
    updated_at?: string;
    children?: PermissionNode[];
  }

  /** `GET /api/auth/menus` 当前用户可见的菜单节点（只有 directory / menu） */
  interface MenuNode {
    id: Id;
    parent_id: Id | null;
    name: string;
    code: string;
    type: Exclude<PermissionType, 'action'>;
    path: string | null;
    icon: string | null;
    is_external: boolean;
    sort: number;
    children?: MenuNode[];
  }

  interface LoginParams {
    username: string;
    password: string;
  }

  interface LoginResult {
    token: string;
    token_type: 'Bearer';
    expires_at: string | null;
  }

  interface CurrentUser {
    id: Id;
    username: string;
    name: string;
    email: string | null;
    phone: string | null;
    avatar: string | null;
    status: Status;
    is_super_admin: boolean;
    department: Brief | null;
    roles: CodedBrief[];
    positions: CodedBrief[];
    /** 实际拥有的权限码全集 */
    permissions: string[];
    data_scope: DataScope;
    unread_notifications_count: number;
    last_login_at: string | null;
  }

  interface ProfileParams {
    name: string;
    email?: string | null;
    phone?: string | null;
    avatar?: string | null;
  }

  interface PasswordParams {
    current_password: string;
    password: string;
    password_confirmation: string;
  }

  /** 站内通知（Laravel database notification，id 为 UUID） */
  interface Notification {
    id: string;
    type: string;
    title: string;
    content: string;
    read_at: string | null;
    created_at: string;
  }

  /** 附件（`POST /api/uploads` 的返回值，也是附件列表的项） */
  interface Attachment {
    id: Id;
    /** 原始文件名 */
    name: string;
    url: string;
    path: string;
    disk: string;
    mime: string;
    /** 字节 */
    size: number;
    uploaded_by: Id | null;
    /** 仅附件列表返回 */
    uploader?: Brief | null;
    created_at: string;
  }

  /** `/api/options/dicts` 中的字典项 */
  interface DictOption {
    label: string;
    value: string;
    /** antd 预设色（green、blue、success…）或十六进制色值 */
    color: string | null;
  }

  /** `/api/options/dicts?codes=a,b` → `{a: [...], b: [...]}` */
  type DictMap = Record<string, DictOption[]>;

  /** `/api/options/settings`：公开系统参数（值已按 type 转换） */
  type PublicSettings = Record<string, unknown>;
}
