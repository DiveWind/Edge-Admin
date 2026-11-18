import type { SessionHandlers } from '../client';

import dayjs from 'dayjs';
import { describe, expect, it, vi } from 'vitest';

import { createRequestClient } from '../client';
import { toListParams } from '../list';

const page = { currentPage: 2, pageSize: 20 };

describe('toListParams', () => {
  it('wraps the page and the search form values', () => {
    expect(
      toListParams(page, { status: 'enabled', username: 'admin' }),
    ).toEqual({
      filter: { status: 'enabled', username: 'admin' },
      page: 2,
      pageSize: 20,
    });
  });

  it('drops empty values', () => {
    expect(
      toListParams(page, {
        a: undefined,
        b: null,
        c: '',
        d: '   ',
        e: [],
        f: [null, undefined],
        g: ['', ''],
      }).filter,
    ).toEqual({});
  });

  it('keeps zero and false, and trims strings', () => {
    expect(
      toListParams(page, { flag: false, name: '  ad  ', sort: 0 }).filter,
    ).toEqual({ flag: false, name: 'ad', sort: 0 });
  });

  it('joins arrays (ranges, multiple choices) with commas', () => {
    expect(
      toListParams(page, {
        created_at: ['2026-01-01', '2026-01-31'],
        roles: ['admin', 'editor'],
      }).filter,
    ).toEqual({
      created_at: '2026-01-01,2026-01-31',
      roles: 'admin,editor',
    });
  });

  it('keeps the position of an open end of a range', () => {
    expect(
      toListParams(page, {
        from: ['2026-01-01', null],
        to: [undefined, '2026-01-31'],
      }).filter,
    ).toEqual({ from: '2026-01-01,', to: ',2026-01-31' });
  });

  it('formats dates as ISO 8601 with the time zone offset', () => {
    const start = dayjs('2026-01-01T00:00:00');
    const end = new Date('2026-01-31T23:59:59');

    expect(toListParams(page, { created_at: [start, end] }).filter).toEqual({
      created_at: `${start.format()},${dayjs(end).format()}`,
    });
    expect(start.format()).toMatch(/^2026-01-01T00:00:00[+-]\d{2}:\d{2}$/);
  });

  it('accepts missing form values', () => {
    expect(toListParams(page, null)).toEqual({
      filter: {},
      page: 2,
      pageSize: 20,
    });
  });
});

describe('list query serialization', () => {
  const handlers: SessionHandlers = {
    confirmPassword: vi.fn(async () => true),
    onUnauthorized: vi.fn(),
    refreshCsrfToken: vi.fn(async () => null),
    showError: vi.fn(),
  };
  const client = createRequestClient('/api/admin', handlers);

  function query(params: unknown) {
    const uri = client.instance.getUri({ params, url: '/system/users' });
    return decodeURIComponent(uri.split('?')[1] ?? '');
  }

  it('sends the filters as filter[field]=value', () => {
    const params = toListParams(
      { currentPage: 1, pageSize: 20 },
      {
        created_at: ['2026-01-01', '2026-01-31'],
        status: 'enabled',
        username: 'a b+c',
      },
    );

    expect(query(params).split('&').toSorted()).toEqual([
      'filter[created_at]=2026-01-01,2026-01-31',
      'filter[status]=enabled',
      'filter[username]=a+b+c',
      'page=1',
      'pageSize=20',
    ]);
  });

  it('encodes + so that it is not read as a space', () => {
    const params = toListParams(page, { username: 'a b+c' });
    const uri = client.instance.getUri({ params, url: '/system/users' });

    expect(uri).toContain('username%5D=a+b%2Bc');
  });

  it('sends nothing for an empty filter', () => {
    expect(query(toListParams(page, {})).split('&').toSorted()).toEqual([
      'page=2',
      'pageSize=20',
    ]);
  });
});
