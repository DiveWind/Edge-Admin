/** 工作台聚合数据（`GET /api/dashboard`） */
export interface DashboardData {
  totals: {
    users: number;
    roles: number;
    departments: number;
    articles: number;
    today_logins: number;
    today_operations: number;
    online_sessions: number;
    attachments: number;
  };
  /** 最近 14 天（含今天，缺失补 0） */
  login_trend: { date: string; success: number; failed: number }[];
  articles_by_category: { category: string; count: number }[];
  users_by_department: { department: string; count: number }[];
  recent_operations: RecentOperation[];
}

export interface RecentOperation {
  id: API.Id;
  user: API.Brief | null;
  method: string;
  path: string;
  summary: string | null;
  status_code: number;
  created_at: string;
}
