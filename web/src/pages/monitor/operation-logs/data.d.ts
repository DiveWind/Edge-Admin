/** 操作日志（`GET /api/operation-logs[/{id}]`），payload 已由后端脱敏 */
export interface OperationLog {
  id: API.Id;
  user_id: API.Id | null;
  /** 该请求的 `X-Request-Id`，可与服务端日志关联；历史记录为 null */
  request_id: string | null;
  user: API.Brief | null;
  method: string;
  path: string;
  route_name: string | null;
  summary: string | null;
  ip: string;
  user_agent: string;
  status_code: number;
  duration_ms: number;
  /** 恒为对象，无内容时为 `{}` */
  payload: Record<string, unknown>;
  created_at: string;
}
