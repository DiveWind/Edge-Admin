import { request } from '@umijs/max';
import type { Announcement, AnnouncementFormValues } from './data';

export function queryAnnouncements(params: API.ListQuery) {
  return request<API.Paginated<Announcement>>('/api/announcements', {
    params,
  });
}

export function createAnnouncement(data: AnnouncementFormValues) {
  return request<API.Resource<Announcement>>('/api/announcements', {
    method: 'POST',
    data,
  });
}

export function updateAnnouncement(id: API.Id, data: AnnouncementFormValues) {
  return request<API.Resource<Announcement>>(`/api/announcements/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deleteAnnouncement(id: API.Id) {
  return request<void>(`/api/announcements/${id}`, { method: 'DELETE' });
}

/** 发布并通过队列给所有启用用户发送站内通知 */
export function publishAnnouncement(id: API.Id) {
  return request<API.Resource<Announcement>>(
    `/api/announcements/${id}/publish`,
    { method: 'POST' },
  );
}
