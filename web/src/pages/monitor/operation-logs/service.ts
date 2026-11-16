import { request } from '@umijs/max';
import type { OperationLog } from './data';

export function queryOperationLogs(params: API.ListQuery) {
  return request<API.Paginated<OperationLog>>('/api/operation-logs', {
    params,
  });
}

export function getOperationLog(id: API.Id) {
  return request<API.Resource<OperationLog>>(`/api/operation-logs/${id}`);
}

export function deleteOperationLog(id: API.Id) {
  return request<void>(`/api/operation-logs/${id}`, { method: 'DELETE' });
}

export function clearOperationLogs() {
  return request<void>('/api/operation-logs', { method: 'DELETE' });
}

/** 写操作的 HTTP 方法（只有非 GET 请求会被记录） */
export const HTTP_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'] as const;

export const methodColor: Record<string, string> = {
  POST: 'green',
  PUT: 'blue',
  PATCH: 'cyan',
  DELETE: 'red',
};
