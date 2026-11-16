import { request } from '@umijs/max';
import type { Role, RoleDetail, RoleFormValues } from './data';

export function queryRoles(params: API.ListQuery) {
  return request<API.Paginated<Role>>('/api/roles', { params });
}

export function getRole(id: API.Id) {
  return request<API.Resource<RoleDetail>>(`/api/roles/${id}`);
}

export function createRole(data: RoleFormValues) {
  return request<API.Resource<Role>>('/api/roles', { method: 'POST', data });
}

export function updateRole(id: API.Id, data: RoleFormValues) {
  return request<API.Resource<Role>>(`/api/roles/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deleteRole(id: API.Id) {
  return request<void>(`/api/roles/${id}`, { method: 'DELETE' });
}
