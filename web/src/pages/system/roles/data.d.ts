/** 角色列表项（`GET /api/roles`） */
export interface Role {
  id: API.Id;
  name: string;
  code: string;
  data_scope: API.DataScope;
  sort: number;
  status: API.Status;
  remark: string | null;
  /** 已分配的用户数（> 0 时不可删除） */
  users_count: number;
  is_super_admin: boolean;
  created_at: string;
  updated_at: string;
}

/** 角色详情（`GET /api/roles/{id}`）额外带授权数据 */
export interface RoleDetail extends Role {
  permission_ids: API.Id[];
  department_ids: API.Id[];
}

export interface RoleFormValues {
  name: string;
  code: string;
  data_scope: API.DataScope;
  sort: number;
  status: API.Status;
  remark?: string | null;
  permission_ids: API.Id[];
  /** 仅 data_scope = custom 时生效 */
  department_ids: API.Id[];
}
