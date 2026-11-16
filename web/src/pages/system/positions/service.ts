import { request } from '@umijs/max';
import type { Position, PositionFormValues } from './data';

export function queryPositions(params: API.ListQuery) {
  return request<API.Paginated<Position>>('/api/positions', { params });
}

export function createPosition(data: PositionFormValues) {
  return request<API.Resource<Position>>('/api/positions', {
    method: 'POST',
    data,
  });
}

export function updatePosition(id: API.Id, data: PositionFormValues) {
  return request<API.Resource<Position>>(`/api/positions/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deletePosition(id: API.Id) {
  return request<void>(`/api/positions/${id}`, { method: 'DELETE' });
}
