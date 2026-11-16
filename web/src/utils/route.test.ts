import { describe, expect, it } from 'vitest';
import type { AppRoute } from '../../config/routes';
import { canAccessPath, findRoute } from './route';

const routes: AppRoute[] = [
  {
    path: '/user',
    layout: false,
    routes: [{ path: '/user/login', name: 'login', component: './user/login' }],
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
    ],
  },
  {
    path: '/message/notifications',
    name: 'notifications',
    component: './message/notifications',
  },
];

const granting =
  (codes: string[]) =>
  (route: AppRoute): boolean =>
    !route.permission ||
    [route.permission].flat().some((code) => codes.includes(code));

describe('findRoute', () => {
  it('finds nested page routes by exact path', () => {
    expect(findRoute(routes, '/system/users')?.component).toBe(
      './system/users',
    );
    expect(findRoute(routes, '/system')).toBeUndefined();
    expect(findRoute(routes, '/missing')).toBeUndefined();
  });
});

describe('canAccessPath', () => {
  it('checks the route permission declared on the static route', () => {
    expect(canAccessPath(routes, '/system/users', granting([]))).toBe(false);
    expect(
      canAccessPath(routes, '/system/users', granting(['system.user.view'])),
    ).toBe(true);
    expect(canAccessPath(routes, '/message/notifications', granting([]))).toBe(
      true,
    );
  });

  it('treats paths without a static page as not accessible', () => {
    expect(canAccessPath(routes, '/custom/page', () => true)).toBe(false);
  });
});
