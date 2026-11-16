import type { MenuDataItem } from '@ant-design/pro-components';
import { describe, expect, it } from 'vitest';
import { buildMenuData, collectMenuPaths, findFirstMenuPath } from './menu';

const node = (
  partial: Partial<API.MenuNode> & Pick<API.MenuNode, 'id' | 'code'>,
): API.MenuNode => ({
  parent_id: null,
  name: partial.code,
  type: 'menu',
  path: null,
  icon: null,
  is_external: false,
  sort: 0,
  children: [],
  ...partial,
});

const menus: API.MenuNode[] = [
  node({
    id: 1,
    code: 'system',
    type: 'directory',
    path: '/system',
    icon: 'SettingOutlined',
    children: [
      node({ id: 2, code: 'system.user', path: '/system/users' }),
      node({ id: 3, code: 'system.role', path: '/system/roles' }),
    ],
  }),
  node({ id: 4, code: 'docs', path: 'https://ant.design', is_external: true }),
  node({ id: 5, code: 'dashboard', path: '/dashboard' }),
];

const staticMenuData: MenuDataItem[] = [
  { path: '/dashboard', name: '工作台' },
  {
    path: '/system',
    name: '系统管理',
    children: [
      { path: '/system/users', name: '用户管理' },
      { path: '/system/roles', name: '角色管理', unaccessible: true },
    ],
  },
  { path: '/account/settings', name: '个人设置', hideInMenu: true },
  { path: '.', name: '404' },
];

const options = {
  translate: (item: API.MenuNode) => item.name.toUpperCase(),
  renderIcon: (name: string | null) => name,
};

describe('buildMenuData', () => {
  it('uses the server menus for structure, names, icons and order', () => {
    const data = buildMenuData(menus, staticMenuData, options);
    const visible = data.filter((item) => !item.hideInMenu);

    expect(visible.map((item) => item.name)).toEqual([
      'SYSTEM',
      'DOCS',
      'DASHBOARD',
    ]);
    expect(visible[0]).toMatchObject({
      key: 'menu-1',
      icon: 'SettingOutlined',
      locale: false,
    });
    // 无权访问的静态路由对应的菜单被剔除
    expect(visible[0].children?.map((item) => item.path)).toEqual([
      '/system/users',
    ]);
    expect(visible[1].path).toBe('https://ant.design');
  });

  it('keeps uncovered static pages as hidden entries for titles and breadcrumbs', () => {
    const data = buildMenuData(menus, staticMenuData, options);

    expect(data.filter((item) => item.hideInMenu)).toEqual([
      { path: '/account/settings', name: '个人设置', hideInMenu: true },
    ]);
    // 服务端隐藏了 /dashboard：静态页面仍保留为隐藏项，可直接访问
    const withoutDashboard = buildMenuData(
      menus.slice(0, 2),
      staticMenuData,
      options,
    );
    expect(
      withoutDashboard.find((item) => item.path === '/dashboard'),
    ).toMatchObject({ hideInMenu: true, name: '工作台' });
  });

  it('drops directories without displayable children', () => {
    const data = buildMenuData(
      [
        node({
          id: 1,
          code: 'system',
          type: 'directory',
          children: [
            node({ id: 3, code: 'system.role', path: '/system/roles' }),
          ],
        }),
      ],
      staticMenuData,
      options,
    );
    expect(data.filter((item) => !item.hideInMenu)).toEqual([]);
  });
});

describe('collectMenuPaths', () => {
  it('collects the paths of menu nodes', () => {
    expect([...collectMenuPaths(menus)]).toEqual([
      '/system/users',
      '/system/roles',
      'https://ant.design',
      '/dashboard',
    ]);
  });
});

describe('findFirstMenuPath', () => {
  it('returns the first accessible internal menu in server order', () => {
    expect(findFirstMenuPath(menus, () => true)).toBe('/system/users');
    expect(findFirstMenuPath(menus, (path) => path === '/dashboard')).toBe(
      '/dashboard',
    );
  });

  it('skips external links and returns undefined when nothing is accessible', () => {
    expect(findFirstMenuPath([menus[1]], () => true)).toBeUndefined();
    expect(findFirstMenuPath(menus, () => false)).toBeUndefined();
  });
});
