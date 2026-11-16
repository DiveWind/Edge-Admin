import { request } from '@umijs/max';

/** 权限点树（只读，需要 system.permission.view）。权限页展示与角色授权共用 */
export function getPermissionTree() {
  return request<API.Collection<API.PermissionNode>>('/api/permissions');
}
