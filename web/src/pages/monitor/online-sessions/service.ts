import { request } from '@umijs/max';
import type { OnlineSession } from './data';

/** 过滤 username ip */
export function queryOnlineSessions(params: API.ListQuery) {
  return request<API.Paginated<OnlineSession>>('/api/online-sessions', {
    params,
  });
}

/** 强制下线（吊销 token）；当前会话 403 */
export function kickOnlineSession(id: API.Id) {
  return request<void>(`/api/online-sessions/${id}`, { method: 'DELETE' });
}
