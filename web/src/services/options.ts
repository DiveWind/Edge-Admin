import { request } from '@umijs/max';

/** 下拉 / 树形选项（契约 §5.6），只返回启用项，仅要求登录 */

export function getDepartmentOptions() {
  return request<API.Collection<API.DepartmentOption>>(
    '/api/options/departments',
  );
}

/** 权限树（同 GET /api/permissions），角色授权用，不需要 system.permission.view */
export function getPermissionOptions() {
  return request<API.Collection<API.PermissionNode>>(
    '/api/options/permissions',
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

/** options 接口 → Select / ProFormSelect 的 `{label, value}` */
export const toSelectOptions = ({ data }: API.Collection<API.Option>) =>
  data.map(({ id, name }) => ({ label: name, value: id }));

/** 字典项（仅启用字典的启用项，按 sort）。页面里请用 `useDict` / `useDicts`，不要直接调用 */
export function getDictOptions(codes: string[]) {
  return request<API.Resource<API.DictMap>>('/api/options/dicts', {
    params: { codes: codes.join(',') },
  });
}

/** 公开系统参数，无需登录（getInitialState 中调用） */
export function getPublicSettings(options?: { skipErrorHandler?: boolean }) {
  return request<API.Resource<API.PublicSettings>>('/api/options/settings', {
    ...options,
  });
}
