import type { PermissionCode } from '../src/access';

/**
 * 静态路由：页面组件（编译期确定）与路由级权限。
 *
 * - **侧边菜单不来自这里**：菜单的结构 / 名称 / 图标 / 顺序 / 显隐以服务端 `GET /api/auth/menus` 为准
 *   （菜单管理页维护，见 src/utils/menu.ts）。服务端菜单通过 `path` 对应到这里的路由；
 *   这里的 `name`（`menu.<父级 name>.<name>`）只用作服务端菜单之外页面（个人设置、隐藏菜单等）的标题与面包屑。
 * - 需要权限的页面写 `access: 'routeFilter'` + `permission`（权限码或权限码数组，满足其一即可）。
 *   无权限时直接访问 URL 渲染 403，对应的服务端菜单也不显示。只要求登录的页面不写 access。
 * - `// edge:routes`、`// edge:routes:<模块>` 是代码生成器（edge:make-module）的锚点，新路由插在它们之前，请勿删除。
 * - 分组路由（只有 routes 没有 component）不要写 access，也不要放无权限要求的 redirect 子路由。
 *
 * @doc https://umijs.org/docs/guides/routes
 */
export interface AppRoute {
  path: string;
  name?: string;
  /** 标题文案 key（默认 `menu.<父级 name>.<name>`），生成器生成的路由指向 `permission.<code>` */
  locale?: string;
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
    component: './dashboard',
    access: 'routeFilter',
    permission: 'dashboard.view',
  },
  {
    path: '/system',
    name: 'system',
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
        permission: 'monitor.scheduled-task.view',
      },
      {
        path: '/monitor/failed-jobs',
        name: 'failed-jobs',
        component: './monitor/failed-jobs',
        access: 'routeFilter',
        permission: 'monitor.failed-job.view',
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
