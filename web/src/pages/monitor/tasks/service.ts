import { request } from '@umijs/max';
import type { FailedJob, ScheduledTask, TaskLog } from './data';

export function getScheduledTasks() {
  return request<API.Collection<ScheduledTask>>('/api/scheduled-tasks');
}

/** 立即执行一次（入队），202 */
export function runScheduledTask(key: string) {
  return request<void>(`/api/scheduled-tasks/${encodeURIComponent(key)}/run`, {
    method: 'POST',
  });
}

export function queryTaskLogs(params: API.ListQuery) {
  return request<API.Paginated<TaskLog>>('/api/scheduled-task-logs', {
    params,
  });
}

/** 契约未说明是否分页，分页 / 非分页响应均可（见 toTableResult） */
export function queryFailedJobs(params: API.ListQuery) {
  return request<API.Paginated<FailedJob> | API.Collection<FailedJob>>(
    '/api/failed-jobs',
    { params },
  );
}

export function retryFailedJob(uuid: string) {
  return request<void>(`/api/failed-jobs/${uuid}/retry`, { method: 'POST' });
}

export function deleteFailedJob(uuid: string) {
  return request<void>(`/api/failed-jobs/${uuid}`, { method: 'DELETE' });
}
