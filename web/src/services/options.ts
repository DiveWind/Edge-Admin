import { request } from '@umijs/max';

/** 下拉 / 树形选项（契约 §5.6），只返回启用项，仅要求登录 */

export function getDepartmentOptions() {
  return request<API.Collection<API.DepartmentOption>>(
    '/api/options/departments',
  );
}

export function getRoleOptions() {
  return request<API.Collection<API.Option>>('/api/options/roles');
}

export function getPositionOptions() {
  return request<API.Collection<API.Option>>('/api/options/positions');
}

export function getArticleCategoryOptions() {
  return request<API.Collection<API.Option>>('/api/options/article-categories');
}

export function getUserOptions() {
  return request<API.Collection<API.Option>>('/api/options/users');
}
