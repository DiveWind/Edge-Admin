/** 岗位（`/api/positions`） */
export interface Position {
  id: API.Id;
  name: string;
  code: string;
  sort: number;
  status: API.Status;
  remark: string | null;
  created_at: string;
}

export interface PositionFormValues {
  name: string;
  code: string;
  sort: number;
  status: API.Status;
  remark?: string | null;
}
