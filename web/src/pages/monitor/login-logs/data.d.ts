/** 登录日志（`GET /api/login-logs`） */
export interface LoginLog {
  id: API.Id;
  user_id: API.Id | null;
  username: string;
  ip: string;
  user_agent: string;
  status: 'success' | 'failed';
  message: string | null;
  created_at: string;
}
