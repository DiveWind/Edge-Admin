/** 操作日志（`GET /api/operation-logs[/{id}]`），payload 已由后端脱敏 */
export interface OperationLog {
  id: API.Id;
  user_id: API.Id | null;
  user: API.Brief | null;
  method: string;
  path: string;
  route_name: string | null;
  summary: string | null;
  ip: string;
  user_agent: string;
  status_code: number;
  duration_ms: number;
  payload: Record<string, unknown> | null;
  created_at: string;
}
