import { describe, expect, it } from 'vitest';
import type { AppRoute } from '../../config/routes';
import { findFirstMenuPath } from './route';

const routes: AppRoute[] = [
  {
    path: '/user',
    layout: false,
    routes: [{ path: '/user/login', name: 'login', component: './user/login' }],
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
    ],
  },
  {
    path: '/message',
    name: 'message',
    routes: [
      {
        path: '/message/notifications',
        name: 'notifications',
        component: './message/notifications',
      },
    ],
  },
  {
    path: '/account/settings',
    name: 'account-settings',
    component: './account/settings',
    hideInMenu: true,
  },
  { path: '/', redirect: '/dashboard' },
];

const granting =
  (codes: string[]) =>
  (route: AppRoute): boolean =>
    !route.permission ||
    [route.permission].flat().some((code) => codes.includes(code));

describe('findFirstMenuPath', () => {
  it('returns the first accessible menu page in menu order', () => {
    expect(findFirstMenuPath(routes, granting(['dashboard.view']))).toBe(
      '/dashboard',
    );
    expect(findFirstMenuPath(routes, granting(['system.user.view']))).toBe(
      '/system/users',
    );
  });

  it('falls back to pages that only require login', () => {
    expect(findFirstMenuPath(routes, granting([]))).toBe(
      '/message/notifications',
    );
  });

  it('returns undefined when nothing is accessible', () => {
    expect(findFirstMenuPath(routes, () => false)).toBeUndefined();
  });
});
