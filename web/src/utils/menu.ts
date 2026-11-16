import type { MenuDataItem } from '@ant-design/pro-components';
import type React from 'react';

/**
 * 侧边菜单：结构 / 名称 / 图标 / 顺序 / 显隐以服务端 `GET /api/auth/menus` 为准，静态路由（config/routes.ts）只提供页面组件。
 *
 * ProLayout 的 `menuDataRender` 收到由静态路由生成的菜单数据（已按 access 标记 `unaccessible`），这里用服务端菜单替换它：
 * - 服务端菜单节点按 `path` 对应静态路由；对应的静态路由无权访问（`unaccessible`）时不显示，没有可显示子菜单的目录也不显示；
 * - 外链菜单（`is_external`，path 为 URL）由 ProLayout 在新窗口打开；
 * - 服务端菜单没有覆盖到的静态页面（个人设置、被隐藏的菜单页面等）以 `hideInMenu` 保留，用于页面标题与面包屑。
 */
export interface MenuRenderOptions {
  /** 节点显示名称（按 code 翻译，见 utils/permission） */
  translate: (node: API.MenuNode) => string;
  /** 图标名 → 图标元素 */
  renderIcon: (name: string | null) => React.ReactNode;
}

/** 静态菜单数据中的所有页面（叶子）节点 */
function staticLeaves(items: MenuDataItem[]): MenuDataItem[] {
  return items.flatMap((item) =>
    item.children?.length
      ? staticLeaves(item.children)
      : item.path?.startsWith('/')
        ? [item]
        : [],
  );
}

/** 服务端菜单中所有菜单节点的 path */
export function collectMenuPaths(menus: API.MenuNode[]): Set<string> {
  const paths = new Set<string>();
  const walk = (nodes: API.MenuNode[]) => {
    for (const node of nodes) {
      if (node.type === 'menu' && node.path) paths.add(node.path);
      walk(node.children ?? []);
    }
  };
  walk(menus);
  return paths;
}

function toMenuItems(
  nodes: API.MenuNode[],
  unaccessible: Set<string>,
  options: MenuRenderOptions,
): MenuDataItem[] {
  return nodes.flatMap((node): MenuDataItem[] => {
    const base: MenuDataItem = {
      key: `menu-${node.id}`,
      name: options.translate(node),
      icon: options.renderIcon(node.icon),
      // 名称已翻译，不再按 `menu.<name>` 查找文案
      locale: false,
    };
    if (node.type === 'directory') {
      const children = toMenuItems(node.children ?? [], unaccessible, options);
      if (!children.length) return [];
      return [{ ...base, path: node.path ?? undefined, children }];
    }
    if (!node.path || (!node.is_external && unaccessible.has(node.path))) {
      return [];
    }
    return [{ ...base, path: node.path }];
  });
}

export function buildMenuData(
  menus: API.MenuNode[],
  staticMenuData: MenuDataItem[],
  options: MenuRenderOptions,
): MenuDataItem[] {
  const leaves = staticLeaves(staticMenuData);
  const unaccessible = new Set(
    leaves
      .filter((leaf) => leaf.unaccessible)
      .map((leaf) => leaf.path as string),
  );
  const covered = collectMenuPaths(menus);
  const hidden = leaves
    .filter((leaf) => !covered.has(leaf.path as string))
    .map((leaf) => ({ ...leaf, hideInMenu: true }));

  return [...toMenuItems(menus, unaccessible, options), ...hidden];
}

/**
 * 按服务端菜单顺序找到第一个可访问的站内页面（跳过外链与目录本身）。
 * 用于 `/` 与登录后的默认落地页，避免无 dashboard.view 的用户先看到 403。
 */
export function findFirstMenuPath(
  menus: API.MenuNode[],
  canAccess: (path: string) => boolean,
): string | undefined {
  for (const node of menus) {
    if (node.type === 'directory') {
      const path = findFirstMenuPath(node.children ?? [], canAccess);
      if (path) return path;
      continue;
    }
    if (node.path && !node.is_external && canAccess(node.path)) {
      return node.path;
    }
  }
  return undefined;
}
