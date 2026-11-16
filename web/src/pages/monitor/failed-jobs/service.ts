import { request } from '@umijs/max';
import type { FailedJob } from './data';

/** 不分页 */
export function getFailedJobs() {
  return request<API.Collection<FailedJob>>('/api/failed-jobs');
}

export function retryFailedJob(uuid: string) {
  return request<void>(`/api/failed-jobs/${uuid}/retry`, { method: 'POST' });
}

export function deleteFailedJob(uuid: string) {
  return request<void>(`/api/failed-jobs/${uuid}`, { method: 'DELETE' });
}
