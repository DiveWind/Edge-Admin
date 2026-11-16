/** 定时任务执行日志（`GET /api/scheduled-task-logs`） */
export interface TaskLog {
  id: API.Id;
  task: string;
  status: 'running' | 'success' | 'failed';
  output: string | null;
  duration_ms: number | null;
  started_at: string;
  finished_at: string | null;
}

/** Laravel Schedule 中注册的任务（`GET /api/scheduled-tasks`） */
export interface ScheduledTask {
  key: string;
  command: string;
  description: string | null;
  expression: string;
  next_run_at: string;
  last_log?: TaskLog | null;
}

/** 失败队列任务（`GET /api/failed-jobs`），exception 为摘要 */
export interface FailedJob {
  id: API.Id;
  uuid: string;
  connection: string;
  queue: string;
  name: string;
  exception: string;
  failed_at: string;
}
