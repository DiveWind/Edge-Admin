export type SettingType = 'string' | 'number' | 'boolean' | 'json';

/** 系统参数（`/api/settings`），value 已按 type 转换 */
export interface Setting {
  id: API.Id;
  group: string;
  key: string;
  name: string;
  type: SettingType;
  value: string | number | boolean | Record<string, unknown> | unknown[] | null;
  is_public: boolean;
  /** 内置参数：不可改 key / type、不可删除 */
  is_system: boolean;
  remark: string | null;
  created_at: string;
  updated_at: string;
}

export interface SettingFormValues {
  group: string;
  key: string;
  name: string;
  type: SettingType;
  value?: Setting['value'];
  is_public?: boolean;
  remark?: string | null;
}
