import { request } from '@umijs/max';
import type { Permission, PermissionFormValues, PermissionQuery } from './data';

/** 菜单树（不分页），过滤 name code type status */
export function queryPermissions(params?: PermissionQuery) {
  return request<API.Collection<Permission>>('/api/permissions', { params });
}

export function getPermission(id: API.Id) {
  return request<API.Resource<Permission>>(`/api/permissions/${id}`);
}

export function createPermission(data: PermissionFormValues) {
  return request<API.Resource<Permission>>('/api/permissions', {
    method: 'POST',
    data,
  });
}

export function updatePermission(id: API.Id, data: PermissionFormValues) {
  return request<API.Resource<Permission>>(`/api/permissions/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deletePermission(id: API.Id) {
  return request<void>(`/api/permissions/${id}`, { method: 'DELETE' });
}

/** 节点 → 提交字段：按类型归一（非菜单无外链，按钮无路由 / 图标 / 显示），用于表单与列表的快捷切换 */
export function toPayload(
  values: Omit<PermissionFormValues, 'remark' | 'parent_id'> & {
    parent_id?: API.Id | null;
    remark?: string | null;
  },
): PermissionFormValues {
  const isMenu = values.type === 'menu';
  const isAction = values.type === 'action';
  return {
    type: values.type,
    parent_id: values.parent_id ?? null,
    name: values.name,
    code: values.code,
    path: isAction || !values.path ? null : values.path,
    icon: isAction || !values.icon ? null : values.icon,
    is_external: isMenu && Boolean(values.is_external),
    is_visible: isAction ? true : values.is_visible,
    status: values.status,
    sort: values.sort ?? 0,
    remark: values.remark || null,
  };
}
