/**
 * 权限定义（Umi access 插件）
 * @see https://umijs.org/docs/max/access
 *
 * 方案：只暴露函数 / 布尔值，不把权限码直接铺成 key。
 * - 路由级：config/routes.ts 中 `access: 'routeFilter'` + 自定义字段 `permission`，
 *   插件会把当前路由传给 routeFilter；未登录或缺少权限码 → 菜单隐藏、直接访问渲染 403。
 * - 按钮级：`const access = useAccess(); access.can('system.user.create')`，
 *   或 `<Access accessible={access.can('...')}>`。
 *
 * 权限码以后端 `/api/auth/me` 返回的 `permissions` 为唯一来源，前端只维护类型用于防止拼写错误。
 */

/**
 * 契约 §3.3 权限点清单（只用于类型检查，防止拼写错误）。新增后端权限点时同步追加。
 * `edge:permission-codes` 是代码生成器的锚点，新模块的权限码插在它之前；末尾的 `| never` 不影响类型，
 * 只是让锚点留在联合类型内部（否则格式化器会把 `;` 移到锚点之前）。两者都请勿删除。
 */
export type PermissionCode =
  | 'dashboard.view'
  | 'system.user.view'
  | 'system.user.create'
  | 'system.user.update'
  | 'system.user.delete'
  | 'system.user.reset-password'
  | 'system.role.view'
  | 'system.role.create'
  | 'system.role.update'
  | 'system.role.delete'
  | 'system.permission.view'
  | 'system.department.view'
  | 'system.department.create'
  | 'system.department.update'
  | 'system.department.delete'
  | 'system.position.view'
  | 'system.position.create'
  | 'system.position.update'
  | 'system.position.delete'
  | 'system.dict.view'
  | 'system.dict.create'
  | 'system.dict.update'
  | 'system.dict.delete'
  | 'system.setting.view'
  | 'system.setting.create'
  | 'system.setting.update'
  | 'system.setting.delete'
  | 'system.attachment.view'
  | 'system.attachment.delete'
  | 'monitor.login-log.view'
  | 'monitor.login-log.delete'
  | 'monitor.operation-log.view'
  | 'monitor.operation-log.delete'
  | 'monitor.scheduled-task.view'
  | 'monitor.scheduled-task.run'
  | 'monitor.failed-job.view'
  | 'monitor.failed-job.retry'
  | 'monitor.failed-job.delete'
  | 'monitor.online-session.view'
  | 'monitor.online-session.delete'
  | 'monitor.system-info.view'
  | 'message.announcement.view'
  | 'message.announcement.create'
  | 'message.announcement.update'
  | 'message.announcement.delete'
  | 'message.announcement.publish'
  | 'content.category.view'
  | 'content.category.create'
  | 'content.category.update'
  | 'content.category.delete'
  | 'content.article.view'
  | 'content.article.create'
  | 'content.article.update'
  | 'content.article.delete'
  // edge:permission-codes
  | never;

/** 路由上的权限声明：单个权限码，或权限码数组（满足其一即可） */
export interface PermissionRoute {
  permission?: PermissionCode | PermissionCode[];
}

export default function access(
  initialState: { currentUser?: API.CurrentUser } | undefined,
) {
  const currentUser = initialState?.currentUser;
  const granted = new Set<string>(currentUser?.permissions ?? []);

  const can = (code: PermissionCode): boolean => granted.has(code);

  return {
    isLoggedIn: Boolean(currentUser),
    can,
    canAny: (codes: PermissionCode[]): boolean => codes.some(can),
    /** 路由过滤器：未登录一律不可访问；未声明 permission 的路由只要求登录 */
    routeFilter: (route: PermissionRoute): boolean => {
      if (!currentUser) return false;
      if (!route.permission) return true;
      const required = Array.isArray(route.permission)
        ? route.permission
        : [route.permission];
      return required.some(can);
    },
  };
}
