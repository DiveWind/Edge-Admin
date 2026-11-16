/** 字典类型（`/api/dict-types`） */
export interface DictType {
  id: API.Id;
  name: string;
  code: string;
  status: API.Status;
  remark: string | null;
  /** 字典项数量（> 0 时不可删除） */
  items_count: number;
  created_at: string;
  updated_at: string;
}

export interface DictTypeFormValues {
  name: string;
  code: string;
  status: API.Status;
  remark?: string | null;
}

/** 字典项（`/api/dict-items?dict_type_id=`） */
export interface DictItem {
  id: API.Id;
  dict_type_id: API.Id;
  label: string;
  value: string;
  color: string | null;
  sort: number;
  status: API.Status;
  remark: string | null;
  created_at: string;
  updated_at: string;
}

export interface DictItemFormValues {
  dict_type_id: API.Id;
  label: string;
  value: string;
  color?: string | null;
  sort?: number;
  status: API.Status;
  remark?: string | null;
}
