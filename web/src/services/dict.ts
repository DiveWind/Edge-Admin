import { createDictLoader } from '@/utils/dict';
import { getDictOptions } from './options';

/**
 * 字典的 react-query key。字典类型 / 字典项增删改后
 * `queryClient.invalidateQueries({ queryKey: dictKeys.all })` 让所有 `useDict` 重新加载。
 */
export const dictKeys = {
  all: ['dicts'] as const,
  code: (code: string) => ['dicts', code] as const,
};

/** 合并同一时刻的多个 code 为一次请求 */
export const loadDict = createDictLoader(
  async (codes) => (await getDictOptions(codes)).data,
);
