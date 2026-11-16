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

interface OptionNode extends API.Option {
  children?: OptionNode[];
}

const flattenOptions = (nodes: OptionNode[]): API.Option[] =>
  nodes.flatMap(({ children, ...option }) => [
    option,
    ...flattenOptions(children ?? []),
  ]);

/**
 * 通用选项接口 `GET /api/options/<resource>`（契约 §5.6，如 `article-categories`、`users`，
 * 以及生成器 `--with-options` 生成的接口）→ `{label, value}`；树形选项（`departments`）展开为平铺列表。
 * 代码生成器的外键下拉 / 列使用：`request={() => fetchSelectOptions('tags')}`。
 */
export async function fetchSelectOptions(resource: string) {
  const { data } = await request<API.Collection<OptionNode>>(
    `/api/options/${resource}`,
  );
  return toSelectOptions({ data: flattenOptions(data) });
}

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
