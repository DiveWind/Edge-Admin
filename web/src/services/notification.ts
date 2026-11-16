import { request } from '@umijs/max';

/** 我的通知（契约 §5.4），头部铃铛与通知中心共用 */

/** react-query key，变更通知后 invalidate `notificationKeys.all` 即可刷新铃铛与列表 */
export const notificationKeys = {
  all: ['notifications'] as const,
  unreadCount: ['notifications', 'unread-count'] as const,
  latestUnread: ['notifications', 'latest-unread'] as const,
};

export function queryNotifications(params: API.ListQuery & { read?: 0 | 1 }) {
  return request<API.Paginated<API.Notification>>('/api/notifications', {
    params,
  });
}

export function getUnreadCount() {
  return request<API.Resource<{ count: number }>>(
    '/api/notifications/unread-count',
  );
}

export function markNotificationRead(id: string) {
  return request<void>(`/api/notifications/${id}/read`, { method: 'POST' });
}

export function markAllNotificationsRead() {
  return request<void>('/api/notifications/read-all', { method: 'POST' });
}

export function deleteNotification(id: string) {
  return request<void>(`/api/notifications/${id}`, { method: 'DELETE' });
}
