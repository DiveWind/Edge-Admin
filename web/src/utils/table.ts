import type { SortOrder } from 'antd/es/table/interface';

/** ProTable 传给 request 的第一个参数：分页 + 搜索表单 + `params` 属性 */
export interface TableParams {
  current?: number;
  pageSize?: number;
  keyword?: string;
  [field: string]: unknown;
}

export type TableSort = Record<string, SortOrder>;

/** ProTable request 需要的返回结构 */
export interface TableResult<T> {
  data: T[];
  total: number;
  success: boolean;
}

type ListResponse<T> = API.Paginated<T> | API.Collection<T>;

const isPrimitive = (value: unknown): value is string | number | boolean =>
  typeof value === 'string' ||
  typeof value === 'number' ||
  typeof value === 'boolean';

/**
 * ProTable 参数 → 契约查询参数：`current/pageSize` → `page/per_page`，
 * sorter → `sort`（降序加 `-` 前缀），空值过滤字段会被丢弃。
 * 非原始值（如日期区间数组）需在列的 `search.transform` 中先转换成契约字段。
 */
export function toListQuery(
  params: TableParams,
  sort: TableSort = {},
): API.ListQuery {
  const { current, pageSize, keyword: _keyword, ...filters } = params;
  const query: API.ListQuery = {};
  if (current) query.page = current;
  if (pageSize) query.per_page = pageSize;

  for (const [field, value] of Object.entries(filters)) {
    if (!isPrimitive(value) || value === '') continue;
    query[field] = typeof value === 'string' ? value.trim() : value;
  }

  const sortFields = Object.entries(sort)
    .filter(([, order]) => order)
    .map(([field, order]) => (order === 'descend' ? `-${field}` : field));
  if (sortFields.length) query.sort = sortFields.join(',');

  return query;
}

/** Laravel 列表响应 → ProTable 结果。分页响应取 `meta.total`，非分页取数组长度 */
export function toTableResult<T>(response: ListResponse<T>): TableResult<T> {
  const total = 'meta' in response ? response.meta.total : response.data.length;
  return { data: response.data, total, success: true };
}

/**
 * 把一个 service 函数包装成 ProTable 的 `request`：
 * `<ProTable request={tableRequest(queryUsers)} />`
 */
export function tableRequest<T>(
  fetcher: (query: API.ListQuery) => Promise<ListResponse<T>>,
) {
  return async (
    params: TableParams,
    sort: TableSort,
  ): Promise<TableResult<T>> =>
    toTableResult(await fetcher(toListQuery(params, sort)));
}

/** 日期区间搜索列的 transform：`[from, to]` → `{created_from, created_to}` */
export const createdRangeTransform = (value: [string, string]) => ({
  created_from: value[0],
  created_to: value[1],
});
