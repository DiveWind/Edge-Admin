/** 岗位（`/api/positions`） */
export interface Position {
  id: API.Id;
  name: string;
  code: string;
  sort: number;
  status: API.Status;
  remark: string | null;
  /** 在岗用户数（> 0 时不可删除） */
  users_count: number;
  created_at: string;
}

export interface PositionFormValues {
  name: string;
  code: string;
  sort: number;
  status: API.Status;
  remark?: string | null;
}
