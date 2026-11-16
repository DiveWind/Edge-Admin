import { request } from '@umijs/max';
import type { ResetPasswordValues, User, UserFormValues } from './data';

export function queryUsers(params: API.ListQuery) {
  return request<API.Paginated<User>>('/api/users', { params });
}

export function getUser(id: API.Id) {
  return request<API.Resource<User>>(`/api/users/${id}`);
}

export function createUser(data: UserFormValues) {
  return request<API.Resource<User>>('/api/users', { method: 'POST', data });
}

export function updateUser(id: API.Id, data: UserFormValues) {
  return request<API.Resource<User>>(`/api/users/${id}`, {
    method: 'PUT',
    data,
  });
}

/** 启停：id=1 → 422 errors.status；禁用自己 → 403 */
export function updateUserStatus(id: API.Id, status: API.Status) {
  return request<API.Resource<User>>(`/api/users/${id}/status`, {
    method: 'PATCH',
    data: { status },
  });
}

export function deleteUser(id: API.Id) {
  return request<void>(`/api/users/${id}`, { method: 'DELETE' });
}

export function resetUserPassword(id: API.Id, data: ResetPasswordValues) {
  return request<void>(`/api/users/${id}/password`, { method: 'PUT', data });
}

/** 详情 → 表单值（编辑回填） */
export function toUserFormValues(user: User): UserFormValues {
  return {
    username: user.username,
    name: user.name,
    email: user.email,
    phone: user.phone,
    avatar: user.avatar,
    department_id: user.department_id ?? user.department?.id ?? null,
    status: user.status,
    role_ids: user.roles.map((role) => role.id),
    position_ids: user.positions.map((position) => position.id),
  };
}
