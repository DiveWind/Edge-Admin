import type { AppRoute } from '../../config/routes';

/** 按路径（精确匹配）查找静态路由中的页面路由 */
export function findRoute(
  routes: AppRoute[],
  path: string,
): AppRoute | undefined {
  for (const route of routes) {
    if (route.routes) {
      const found = findRoute(route.routes, path);
      if (found) return found;
      continue;
    }
    if (route.path === path && route.component) return route;
  }
  return undefined;
}

/**
 * 某个路径的页面是否可访问：必须有对应的静态页面路由（服务端菜单指向不存在的页面时视为不可访问），
 * 且通过路由上的权限声明（`access: 'routeFilter'`）。
 */
export function canAccessPath(
  routes: AppRoute[],
  path: string,
  canAccessRoute: (route: AppRoute) => boolean,
): boolean {
  const route = findRoute(routes, path);
  return Boolean(route && route.layout !== false && canAccessRoute(route));
}
