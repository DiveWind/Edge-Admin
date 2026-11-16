import { request } from '@umijs/max';
import type {
  DictItem,
  DictItemFormValues,
  DictType,
  DictTypeFormValues,
} from './data';

export function queryDictTypes(params: API.ListQuery) {
  return request<API.Paginated<DictType>>('/api/dict-types', { params });
}

export function createDictType(data: DictTypeFormValues) {
  return request<API.Resource<DictType>>('/api/dict-types', {
    method: 'POST',
    data,
  });
}

export function updateDictType(id: API.Id, data: DictTypeFormValues) {
  return request<API.Resource<DictType>>(`/api/dict-types/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deleteDictType(id: API.Id) {
  return request<void>(`/api/dict-types/${id}`, { method: 'DELETE' });
}

/** `dict_type_id` 必填 */
export function queryDictItems(
  params: API.ListQuery & { dict_type_id: API.Id },
) {
  return request<API.Paginated<DictItem>>('/api/dict-items', { params });
}

export function createDictItem(data: DictItemFormValues) {
  return request<API.Resource<DictItem>>('/api/dict-items', {
    method: 'POST',
    data,
  });
}

export function updateDictItem(id: API.Id, data: DictItemFormValues) {
  return request<API.Resource<DictItem>>(`/api/dict-items/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deleteDictItem(id: API.Id) {
  return request<void>(`/api/dict-items/${id}`, { method: 'DELETE' });
}
