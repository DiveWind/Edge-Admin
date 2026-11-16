import type { AppRoute } from '../../config/routes';

/**
 * 按菜单顺序找到第一个可访问的页面路径（跳过 layout:false、隐藏菜单、redirect 与分组本身）。
 * 用于 `/` 与登录后的默认落地页，避免无 dashboard.view 的用户先看到 403。
 */
export function findFirstMenuPath(
  routes: AppRoute[],
  canAccess: (route: AppRoute) => boolean,
): string | undefined {
  for (const route of routes) {
    if (route.layout === false || route.hideInMenu || route.redirect) continue;
    if (route.routes) {
      const path = findFirstMenuPath(route.routes, canAccess);
      if (path) return path;
      continue;
    }
    if (route.name && route.component && canAccess(route)) return route.path;
  }
  return undefined;
}
