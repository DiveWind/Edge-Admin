import { request } from '@umijs/max';

/** 权限点树（只读，需要 system.permission.view），权限管理页使用；角色授权用 options/permissions */
export function getPermissionTree() {
  return request<API.Collection<API.PermissionNode>>('/api/permissions');
}
