import { request } from '@umijs/max';
import type { Setting, SettingFormValues } from './data';

export function querySettings(params: API.ListQuery) {
  return request<API.Paginated<Setting>>('/api/settings', { params });
}

export function createSetting(data: SettingFormValues) {
  return request<API.Resource<Setting>>('/api/settings', {
    method: 'POST',
    data,
  });
}

export function updateSetting(id: API.Id, data: SettingFormValues) {
  return request<API.Resource<Setting>>(`/api/settings/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deleteSetting(id: API.Id) {
  return request<void>(`/api/settings/${id}`, { method: 'DELETE' });
}

/** 已有分组（取前 100 条参数去重），用于分组筛选与新建时的候选 */
export async function getSettingGroups(): Promise<string[]> {
  const { data } = await querySettings({ per_page: 100 });
  return [...new Set(data.map((setting) => setting.group))].sort();
}
