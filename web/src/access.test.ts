import { describe, expect, it } from 'vitest';
import access from './access';

const makeUser = (permissions: string[]): API.CurrentUser => ({
  id: 2,
  username: 'editor',
  name: 'Editor',
  email: null,
  phone: null,
  avatar: null,
  status: 'active',
  is_super_admin: false,
  department: null,
  roles: [],
  positions: [],
  permissions,
  data_scope: 'self',
  unread_notifications_count: 0,
  last_login_at: null,
});

describe('access', () => {
  it('denies everything when not logged in', () => {
    const result = access(undefined);
    expect(result.isLoggedIn).toBe(false);
    expect(result.can('dashboard.view')).toBe(false);
    expect(result.routeFilter({})).toBe(false);
    expect(result.routeFilter({ permission: 'dashboard.view' })).toBe(false);
  });

  it('checks button-level permissions against currentUser.permissions', () => {
    const result = access({
      currentUser: makeUser(['system.user.view', 'system.user.create']),
    });
    expect(result.can('system.user.create')).toBe(true);
    expect(result.can('system.user.delete')).toBe(false);
    expect(result.canAny(['system.user.delete', 'system.user.view'])).toBe(
      true,
    );
  });

  it('filters routes by declared permission', () => {
    const result = access({ currentUser: makeUser(['system.role.view']) });
    expect(result.routeFilter({ permission: 'system.role.view' })).toBe(true);
    expect(result.routeFilter({ permission: 'system.user.view' })).toBe(false);
    // 数组：满足其一即可
    expect(
      result.routeFilter({
        permission: ['monitor.failed-job.view', 'system.role.view'],
      }),
    ).toBe(true);
    // 未声明 permission：只要求登录
    expect(result.routeFilter({})).toBe(true);
  });

  it('does not treat the super admin flag as an implicit grant', () => {
    const user = { ...makeUser([]), is_super_admin: true };
    expect(access({ currentUser: user }).can('dashboard.view')).toBe(false);
  });
});
