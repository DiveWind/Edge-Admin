import { describe, expect, it, vi } from 'vitest';
import {
  createDictLoader,
  findDictItem,
  normalizeCodes,
  toValueEnum,
  toValueEnumItem,
} from './dict';

const items: API.DictOption[] = [
  { label: '原创', value: 'original', color: 'success' },
  { label: '转载', value: 'reprint', color: 'blue' },
  { label: '翻译', value: 'translation', color: '#722ed1' },
  { label: '其他', value: 'other', color: null },
];

describe('toValueEnumItem', () => {
  it('maps semantic colors to Badge status', () => {
    expect(toValueEnumItem(items[0])).toEqual({
      text: '原创',
      status: 'Success',
    });
    expect(
      toValueEnumItem({ label: 'x', value: 'x', color: 'default' }),
    ).toEqual({ text: 'x', status: 'Default' });
  });

  it('passes preset / hex colors through as color', () => {
    expect(toValueEnumItem(items[1])).toEqual({ text: '转载', color: 'blue' });
    expect(toValueEnumItem(items[2])).toEqual({
      text: '翻译',
      color: '#722ed1',
    });
  });

  it('omits color when empty', () => {
    expect(toValueEnumItem(items[3])).toEqual({ text: '其他' });
    expect(toValueEnumItem({ label: 'y', value: 'y', color: ' ' })).toEqual({
      text: 'y',
    });
  });
});

describe('toValueEnum', () => {
  it('keeps backend sort order, even for integer-like values', () => {
    const valueEnum = toValueEnum([
      { label: '十', value: '10', color: null },
      { label: '二', value: '2', color: null },
    ]);
    expect([...valueEnum.keys()]).toEqual(['10', '2']);
  });
});

describe('findDictItem', () => {
  it('finds by value and ignores empty values', () => {
    expect(findDictItem(items, 'reprint')?.label).toBe('转载');
    expect(findDictItem(items, 'missing')).toBeUndefined();
    expect(findDictItem(items, null)).toBeUndefined();
    expect(findDictItem(items, '')).toBeUndefined();
  });
});

describe('normalizeCodes', () => {
  it('trims, dedupes and sorts', () => {
    expect(normalizeCodes([' b', 'a', 'b', ''])).toEqual(['a', 'b']);
  });
});

describe('createDictLoader', () => {
  it('batches codes requested in the same tick into one request', async () => {
    const fetcher = vi.fn(async (codes: string[]) =>
      Object.fromEntries(codes.map((code) => [code, [items[0]]])),
    );
    const load = createDictLoader(fetcher);
    const [a, b, a2] = await Promise.all([load('b'), load('a'), load('b')]);
    expect(fetcher).toHaveBeenCalledTimes(1);
    expect(fetcher).toHaveBeenCalledWith(['a', 'b']);
    expect(a).toEqual([items[0]]);
    expect(b).toEqual([items[0]]);
    expect(a2).toEqual([items[0]]);
  });

  it('resolves unknown codes with an empty list', async () => {
    const load = createDictLoader(async () => ({}));
    await expect(load('missing')).resolves.toEqual([]);
  });

  it('rejects every waiter of a failed batch, then starts a new batch', async () => {
    const fetcher = vi
      .fn<(codes: string[]) => Promise<API.DictMap>>()
      .mockRejectedValueOnce(new Error('boom'))
      .mockResolvedValueOnce({ a: [items[1]] });
    const load = createDictLoader(fetcher);
    await expect(Promise.all([load('a'), load('b')])).rejects.toThrow('boom');
    await expect(load('a')).resolves.toEqual([items[1]]);
    expect(fetcher).toHaveBeenCalledTimes(2);
  });
});
