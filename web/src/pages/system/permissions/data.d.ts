/** 菜单树节点（`GET /api/permissions`） */
export type Permission = API.PermissionNode;

/** 新建 / 编辑提交的字段（与类型无关的字段由 `toPayload` 归一） */
export interface PermissionFormValues {
  type: API.PermissionType;
  parent_id: API.Id | null;
  name: string;
  code: string;
  path: string | null;
  icon: string | null;
  is_external: boolean;
  is_visible: boolean;
  status: API.Status;
  sort: number;
  remark: string | null;
}

export interface PermissionQuery {
  name?: string;
  code?: string;
  type?: API.PermissionType;
  status?: API.Status;
}
