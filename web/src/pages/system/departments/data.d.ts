/** 部门树节点（`GET /api/departments`，不分页） */
export interface Department {
  id: API.Id;
  parent_id: API.Id | null;
  name: string;
  leader_id: API.Id | null;
  leader: API.Brief | null;
  sort: number;
  status: API.Status;
  created_at: string;
  children?: Department[];
}

export interface DepartmentFormValues {
  parent_id?: API.Id | null;
  name: string;
  leader_id?: API.Id | null;
  sort: number;
  status: API.Status;
}
