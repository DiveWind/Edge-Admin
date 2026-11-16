import { request } from '@umijs/max';
import type { ScheduledTask, TaskLog } from './data';

export function getScheduledTasks() {
  return request<API.Collection<ScheduledTask>>('/api/scheduled-tasks');
}

/** 立即执行一次（入队），202 `{message}` */
export function runScheduledTask(key: string) {
  return request<{ message: string }>(
    `/api/scheduled-tasks/${encodeURIComponent(key)}/run`,
    {
      method: 'POST',
    },
  );
}

export function queryTaskLogs(params: API.ListQuery) {
  return request<API.Paginated<TaskLog>>('/api/scheduled-task-logs', {
    params,
  });
}
