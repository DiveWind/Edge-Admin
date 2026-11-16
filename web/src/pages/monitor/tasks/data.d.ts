/** 定时任务执行日志（`GET /api/scheduled-task-logs`） */
export interface TaskLog {
  id: API.Id;
  /** 命令，如 `model:prune` */
  task: string;
  status: 'running' | 'success' | 'failed';
  output: string | null;
  duration_ms: number | null;
  started_at: string;
  finished_at: string | null;
}

/** Laravel Schedule 中注册的任务（`GET /api/scheduled-tasks`，不分页） */
export interface ScheduledTask {
  /** 命令 slug，如 `model-prune` */
  key: string;
  command: string;
  description: string | null;
  expression: string;
  next_run_at: string;
  last_log?: TaskLog | null;
}

/** 失败队列任务（`GET /api/failed-jobs`），exception 为摘要；id 与 uuid 相同 */
export interface FailedJob {
  id: string;
  uuid: string;
  connection: string;
  queue: string;
  name: string;
  exception: string;
  failed_at: string;
}
