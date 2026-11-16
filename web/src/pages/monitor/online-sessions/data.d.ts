/** 在线会话（未过期的登录 token，`/api/online-sessions`） */
export interface OnlineSession {
  id: API.Id;
  user: (API.Brief & { username: string }) | null;
  ip: string | null;
  user_agent: string | null;
  last_used_at: string | null;
  created_at: string;
  expires_at: string | null;
  /** 发起本次请求的会话（不可强制下线） */
  is_current: boolean;
}
