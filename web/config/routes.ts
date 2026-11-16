import type { PermissionCode } from '../src/access';

/**
 * 声明式路由，同时生成菜单。
 *
 * - `name` 对应 `menu.<父级 name>.<name>` 国际化 key（见 src/locales/<lang>/menu.ts）
 * - 需要权限的页面写 `access: 'routeFilter'` + `permission`（权限码或权限码数组，满足其一即可）。
 *   无权限时菜单自动隐藏，直接访问 URL 渲染 403。只要求登录的页面不写 access。
 * - `// edge:routes`、`// edge:routes:<模块>` 是代码生成器（edge:make-module）的锚点，新路由插在它们之前，请勿删除。
 * - 分组路由（只有 routes 没有 component）不要写 access，也不要放无权限要求的 redirect 子路由，
 *   否则"所有子路由都不可访问时隐藏分组"的判定会失效。
 *
 * @doc https://umijs.org/docs/guides/routes
 */
export interface AppRoute {
  path: string;
  name?: string;
  icon?: string;
  component?: string;
  redirect?: string;
  layout?: false;
  hideInMenu?: boolean;
  access?: 'routeFilter';
  permission?: PermissionCode | PermissionCode[];
  routes?: AppRoute[];
}

const routes: AppRoute[] = [
  {
    path: '/user',
    layout: false,
    routes: [{ name: 'login', path: '/user/login', component: './user/login' }],
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    icon: 'dashboard',
    component: './dashboard',
    access: 'routeFilter',
    permission: 'dashboard.view',
  },
  {
    path: '/system',
    name: 'system',
    icon: 'setting',
    routes: [
      {
        path: '/system/users',
        name: 'users',
        component: './system/users',
        access: 'routeFilter',
        permission: 'system.user.view',
      },
      {
        path: '/system/roles',
        name: 'roles',
        component: './system/roles',
        access: 'routeFilter',
        permission: 'system.role.view',
      },
      {
        path: '/system/permissions',
        name: 'permissions',
        component: './system/permissions',
        access: 'routeFilter',
        permission: 'system.permission.view',
      },
      {
        path: '/system/departments',
        name: 'departments',
        component: './system/departments',
        access: 'routeFilter',
        permission: 'system.department.view',
      },
      {
        path: '/system/positions',
        name: 'positions',
        component: './system/positions',
        access: 'routeFilter',
        permission: 'system.position.view',
      },
      {
        path: '/system/dicts',
        name: 'dicts',
        component: './system/dicts',
        access: 'routeFilter',
        permission: 'system.dict.view',
      },
      {
        path: '/system/settings',
        name: 'settings',
        component: './system/settings',
        access: 'routeFilter',
        permission: 'system.setting.view',
      },
      {
        path: '/system/attachments',
        name: 'attachments',
        component: './system/attachments',
        access: 'routeFilter',
        permission: 'system.attachment.view',
      },
      // edge:routes:system
    ],
  },
  {
    path: '/monitor',
    name: 'monitor',
    icon: 'monitor',
    routes: [
      {
        path: '/monitor/login-logs',
        name: 'login-logs',
        component: './monitor/login-logs',
        access: 'routeFilter',
        permission: 'monitor.login-log.view',
      },
      {
        path: '/monitor/operation-logs',
        name: 'operation-logs',
        component: './monitor/operation-logs',
        access: 'routeFilter',
        permission: 'monitor.operation-log.view',
      },
      {
        path: '/monitor/tasks',
        name: 'tasks',
        component: './monitor/tasks',
        access: 'routeFilter',
        permission: ['monitor.scheduled-task.view', 'monitor.failed-job.view'],
      },
      {
        path: '/monitor/online-sessions',
        name: 'online-sessions',
        component: './monitor/online-sessions',
        access: 'routeFilter',
        permission: 'monitor.online-session.view',
      },
      {
        path: '/monitor/system-info',
        name: 'system-info',
        component: './monitor/system-info',
        access: 'routeFilter',
        permission: 'monitor.system-info.view',
      },
      // edge:routes:monitor
    ],
  },
  {
    path: '/message',
    name: 'message',
    icon: 'message',
    routes: [
      {
        path: '/message/announcements',
        name: 'announcements',
        component: './message/announcements',
        access: 'routeFilter',
        permission: 'message.announcement.view',
      },
      {
        path: '/message/notifications',
        name: 'notifications',
        component: './message/notifications',
      },
      // edge:routes:message
    ],
  },
  {
    path: '/content',
    name: 'content',
    icon: 'read',
    routes: [
      {
        path: '/content/articles',
        name: 'articles',
        component: './content/articles',
        access: 'routeFilter',
        permission: 'content.article.view',
      },
      {
        path: '/content/categories',
        name: 'categories',
        component: './content/categories',
        access: 'routeFilter',
        permission: 'content.category.view',
      },
      // edge:routes:content
    ],
  },
  // edge:routes
  {
    path: '/account/settings',
    name: 'account-settings',
    component: './account/settings',
    hideInMenu: true,
  },
  {
    path: '/exception',
    hideInMenu: true,
    routes: [
      { path: '/exception/403', component: './exception/403' },
      { path: '/exception/404', component: './exception/404' },
      { path: '/exception/500', component: './exception/500' },
    ],
  },
  // 落地页：跳到第一个有权限的菜单（src/pages/home）
  { path: '/', component: './home' },
  { path: '*', layout: false, component: './exception/404' },
];

export default routes;
