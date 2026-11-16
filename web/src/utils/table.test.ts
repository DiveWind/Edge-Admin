import { describe, expect, it, vi } from 'vitest';
import { tableRequest, toListQuery, toTableResult } from './table';

describe('toListQuery', () => {
  it('maps pagination to page / per_page', () => {
    expect(toListQuery({ current: 2, pageSize: 20 })).toEqual({
      page: 2,
      per_page: 20,
    });
  });

  it('keeps primitive filters, trims strings and drops empty values', () => {
    expect(
      toListQuery({
        current: 1,
        pageSize: 15,
        keyword: 'ignored',
        username: '  admin ',
        status: 'active',
        department_id: 3,
        empty: '',
        missing: undefined,
        nothing: null,
        range: ['2026-01-01', '2026-01-31'],
      }),
    ).toEqual({
      page: 1,
      per_page: 15,
      username: 'admin',
      status: 'active',
      department_id: 3,
    });
  });

  it('converts sorter to contract sort string', () => {
    expect(toListQuery({}, { created_at: 'descend' })).toEqual({
      sort: '-created_at',
    });
    expect(toListQuery({}, { sort: 'ascend' })).toEqual({ sort: 'sort' });
    expect(toListQuery({}, { sort: null })).toEqual({});
  });
});

describe('toTableResult', () => {
  it('reads total from paginated meta', () => {
    const response: API.Paginated<{ id: number }> = {
      data: [{ id: 1 }],
      links: { first: null, last: null, prev: null, next: null },
      meta: {
        current_page: 1,
        per_page: 15,
        total: 42,
        last_page: 3,
        from: 1,
        to: 15,
      },
    };
    expect(toTableResult(response)).toEqual({
      data: [{ id: 1 }],
      total: 42,
      success: true,
    });
  });

  it('falls back to array length for plain collections', () => {
    expect(toTableResult({ data: [{ id: 1 }, { id: 2 }] })).toEqual({
      data: [{ id: 1 }, { id: 2 }],
      total: 2,
      success: true,
    });
  });
});

describe('tableRequest', () => {
  it('passes converted query to the fetcher', async () => {
    const fetcher = vi.fn().mockResolvedValue({ data: [] });
    const request = tableRequest(fetcher);

    await request(
      { current: 3, pageSize: 10, name: 'a' },
      { created_at: 'ascend' },
    );

    expect(fetcher).toHaveBeenCalledWith({
      page: 3,
      per_page: 10,
      name: 'a',
      sort: 'created_at',
    });
  });
});
