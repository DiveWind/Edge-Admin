import { request } from '@umijs/max';
import type { Department, DepartmentFormValues } from './data';

export function queryDepartments(params: API.ListQuery) {
  return request<API.Collection<Department>>('/api/departments', { params });
}

export function createDepartment(data: DepartmentFormValues) {
  return request<API.Resource<Department>>('/api/departments', {
    method: 'POST',
    data,
  });
}

export function updateDepartment(id: API.Id, data: DepartmentFormValues) {
  return request<API.Resource<Department>>(`/api/departments/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deleteDepartment(id: API.Id) {
  return request<void>(`/api/departments/${id}`, { method: 'DELETE' });
}
