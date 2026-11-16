import type { ProSchemaValueEnumType } from '@ant-design/pro-components';

/**
 * 字典数据转换（纯函数，便于单测）。字典项的 `color` 可以是：
 * - 语义色 `success` / `processing` / `error` / `warning` / `default` → ProTable 的 Badge `status`
 * - antd 预设色（`green`、`blue`…）或十六进制色值 → Badge / Tag 的 `color`
 */
const STATUS_COLORS = {
  success: 'Success',
  processing: 'Processing',
  error: 'Error',
  warning: 'Warning',
  default: 'Default',
} as const;

type SemanticColor = keyof typeof STATUS_COLORS;

const isSemanticColor = (color: string): color is SemanticColor =>
  Object.hasOwn(STATUS_COLORS, color);

/** 单个字典项 → ProTable / ProForm 的 valueEnum 项 */
export function toValueEnumItem(item: API.DictOption): ProSchemaValueEnumType {
  const color = item.color?.trim();
  if (!color) return { text: item.label };
  if (isSemanticColor(color)) {
    return { text: item.label, status: STATUS_COLORS[color] };
  }
  return { text: item.label, color };
}

/**
 * 字典项 → valueEnum。用 Map 保持后端的 sort 顺序
 * （普通对象会把 `'1'`、`'2'` 这类整数字符串键重新排序）。
 */
export function toValueEnum(
  items: API.DictOption[],
): Map<string, ProSchemaValueEnumType> {
  return new Map(items.map((item) => [item.value, toValueEnumItem(item)]));
}

/** 按 value 找字典项；value 为空或不在字典中时返回 undefined */
export function findDictItem(
  items: API.DictOption[],
  value: string | null | undefined,
): API.DictOption | undefined {
  if (value === null || value === undefined || value === '') return undefined;
  return items.find((item) => item.value === String(value));
}

/** 去重、去空白并排序，作为批量请求的 codes 参数与缓存键 */
export function normalizeCodes(codes: readonly string[]): string[] {
  return [...new Set(codes.map((code) => code.trim()).filter(Boolean))].sort();
}

type DictFetcher = (codes: string[]) => Promise<API.DictMap>;

/**
 * 字典批量加载器：同一事件循环内请求的多个 code 合并为一次 `GET /api/options/dicts?codes=a,b`。
 * 每个 code 的缓存与去重交给 react-query（见 `useDict`），这里只负责合并请求。
 */
export function createDictLoader(fetcher: DictFetcher) {
  let queue: Map<
    string,
    {
      resolve: (items: API.DictOption[]) => void;
      reject: (error: unknown) => void;
    }[]
  > | null = null;

  const flush = async () => {
    const batch = queue;
    queue = null;
    if (!batch) return;
    try {
      const data = await fetcher(normalizeCodes([...batch.keys()]));
      for (const [code, waiters] of batch) {
        for (const waiter of waiters) waiter.resolve(data[code] ?? []);
      }
    } catch (error) {
      for (const waiters of batch.values()) {
        for (const waiter of waiters) waiter.reject(error);
      }
    }
  };

  return (code: string): Promise<API.DictOption[]> =>
    new Promise((resolve, reject) => {
      if (!queue) {
        queue = new Map();
        setTimeout(flush, 0);
      }
      const waiters = queue.get(code) ?? [];
      waiters.push({ resolve, reject });
      queue.set(code, waiters);
    });
}
