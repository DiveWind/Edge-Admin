import { request } from '@umijs/max';
import type { Category, CategoryFormValues } from './data';

export function queryCategories(params: API.ListQuery) {
  return request<API.Paginated<Category>>('/api/article-categories', {
    params,
  });
}

export function createCategory(data: CategoryFormValues) {
  return request<API.Resource<Category>>('/api/article-categories', {
    method: 'POST',
    data,
  });
}

export function updateCategory(id: API.Id, data: CategoryFormValues) {
  return request<API.Resource<Category>>(`/api/article-categories/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deleteCategory(id: API.Id) {
  return request<void>(`/api/article-categories/${id}`, { method: 'DELETE' });
}
