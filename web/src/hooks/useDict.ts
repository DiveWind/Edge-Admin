import type { ProSchemaValueEnumType } from '@ant-design/pro-components';
import { useQueries } from '@tanstack/react-query';
import { useMemo } from 'react';
import { dictKeys, loadDict } from '@/services/dict';
import { findDictItem, toValueEnum } from '@/utils/dict';

/** 字典变更不频繁：10 分钟内复用缓存；本端增删改字典后会主动失效（dictKeys.all） */
const STALE_TIME = 10 * 60_000;

export interface Dict {
  /** Select / Radio 的 options（`{label, value, color}`，按字典 sort） */
  options: API.DictOption[];
  /** ProTable / ProForm 的 valueEnum（含颜色 → Badge status / color） */
  valueEnum: Map<string, ProSchemaValueEnumType>;
  /** 按 value 取字典项 */
  find: (value: string | null | undefined) => API.DictOption | undefined;
  loading: boolean;
}

const EMPTY: API.DictOption[] = [];

function toDict(items: API.DictOption[], loading: boolean): Dict {
  return {
    options: items,
    valueEnum: toValueEnum(items),
    find: (value) => findDictItem(items, value),
    loading,
  };
}

/**
 * 批量取字典：`const { article_source, common_yes_no } = useDicts(['article_source', 'common_yes_no'])`。
 * 每个 code 独立缓存（react-query 去重），同一时刻的多个 code 合并为一次请求。
 */
export function useDicts<C extends string>(
  codes: readonly C[],
): Record<C, Dict> {
  const results = useQueries({
    queries: codes.map((code) => ({
      queryKey: dictKeys.code(code),
      queryFn: () => loadDict(code),
      staleTime: STALE_TIME,
    })),
  });

  const signature = results
    .map((result) => `${result.dataUpdatedAt}:${result.isLoading}`)
    .join('|');

  // results 每次渲染都是新数组，用 signature（数据更新时间 + 加载态）作为依赖
  return useMemo(
    () =>
      Object.fromEntries(
        codes.map((code, index) => [
          code,
          toDict(
            results[index]?.data ?? EMPTY,
            results[index]?.isLoading ?? false,
          ),
        ]),
      ) as Record<C, Dict>,
    [codes.join(','), signature],
  );
}

/** 单个字典：`const source = useDict('article_source')` */
export function useDict(code: string): Dict {
  return useDicts([code])[code];
}
