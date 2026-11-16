/** 用户列表项 / 详情（`GET /api/users`、`GET /api/users/{id}`） */
export interface User {
  id: API.Id;
  username: string;
  name: string;
  email: string | null;
  phone: string | null;
  avatar: string | null;
  status: API.Status;
  department_id?: API.Id | null;
  department: API.Brief | null;
  roles: API.CodedBrief[];
  positions: API.CodedBrief[];
  last_login_at: string | null;
  last_login_ip: string | null;
  created_at: string;
  updated_at: string;
}

/** 新建 / 编辑提交体（编辑时无 password） */
export interface UserFormValues {
  username: string;
  name: string;
  password?: string;
  email?: string | null;
  phone?: string | null;
  avatar?: string | null;
  department_id?: API.Id | null;
  status: API.Status;
  role_ids: API.Id[];
  position_ids: API.Id[];
}

export interface ResetPasswordValues {
  password: string;
  password_confirmation: string;
}
