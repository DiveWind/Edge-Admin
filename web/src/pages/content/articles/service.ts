import { request } from '@umijs/max';
import type { Article, ArticleFormValues } from './data';

export function queryArticles(params: API.ListQuery) {
  return request<API.Paginated<Article>>('/api/articles', { params });
}

export function getArticle(id: API.Id) {
  return request<API.Resource<Article>>(`/api/articles/${id}`);
}

export function createArticle(data: ArticleFormValues) {
  return request<API.Resource<Article>>('/api/articles', {
    method: 'POST',
    data,
  });
}

export function updateArticle(id: API.Id, data: ArticleFormValues) {
  return request<API.Resource<Article>>(`/api/articles/${id}`, {
    method: 'PUT',
    data,
  });
}

export function deleteArticle(id: API.Id) {
  return request<void>(`/api/articles/${id}`, { method: 'DELETE' });
}
