import { request } from '@umijs/max';
import type { LoginLog } from './data';

export function queryLoginLogs(params: API.ListQuery) {
  return request<API.Paginated<LoginLog>>('/api/login-logs', { params });
}

export function deleteLoginLog(id: API.Id) {
  return request<void>(`/api/login-logs/${id}`, { method: 'DELETE' });
}

export function clearLoginLogs() {
  return request<void>('/api/login-logs', { method: 'DELETE' });
}
