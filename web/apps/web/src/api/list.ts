/**
 * 列表查询（契约见 docs/backend-design.md §6.3）：`page`、`pageSize`、`filter[字段]`，返回 `{ items, total }`。
 *
 * 用法（vxe-table 的 proxyConfig）：
 * ```ts
 * query: ({ page }, formValues) => getUserListApi(toListParams(page, formValues)),
 * ```
 * 请求客户端按 axios 默认方式序列化嵌套对象，即 `filter[status]=enabled`。
 */
import dayjs from 'dayjs';

export namespace ListApi {
  /** 分页列表的返回值 */
  export interface Result<T> {
    items: T[];
    total: number;
  }

  /** 列表查询参数（filter 中只有非空值；区间为 `起,止`） */
  export interface Params {
    filter: Record<string, boolean | number | string>;
    page: number;
    pageSize: number;
  }

  /** vxe-table 代理查询传入的分页信息 */
  export interface Page {
    currentPage: number;
    pageSize: number;
  }
}

/** 单个值转成查询字符串中的值：日期（dayjs / Date）输出带时区偏移的 ISO 8601，字符串去掉首尾空白 */
function toScalar(value: unknown): boolean | number | string | undefined {
  if (value === null || value === undefined) {
    return undefined;
  }
  if (dayjs.isDayjs(value) || value instanceof Date) {
    return dayjs(value).format();
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    return trimmed === '' ? undefined : trimmed;
  }
  if (typeof value === 'number' || typeof value === 'boolean') {
    return value;
  }
  return String(value);
}

/**
 * 把搜索表单的值包装成列表查询参数：去掉 undefined、null、空字符串与空数组；
 * 数组（时间区间、多选）合并为逗号分隔的字符串，区间的一端为空时保留位置（`起,` / `,止`），两端都为空时去掉。
 */
export function toListParams(
  page: ListApi.Page,
  formValues: null | Record<string, unknown> = {},
): ListApi.Params {
  const filter: ListApi.Params['filter'] = {};

  for (const [key, value] of Object.entries(formValues ?? {})) {
    if (Array.isArray(value)) {
      const items = value.map((item) => toScalar(item) ?? '');
      if (items.some((item) => item !== '')) {
        filter[key] = items.join(',');
      }
      continue;
    }
    const scalar = toScalar(value);
    if (scalar !== undefined) {
      filter[key] = scalar;
    }
  }

  return { filter, page: page.currentPage, pageSize: page.pageSize };
}
