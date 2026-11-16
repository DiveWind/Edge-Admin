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
