import dayjs from 'dayjs';
import { describe, expect, it, vi } from 'vitest';
import {
  clearFieldErrors,
  jsonRule,
  parseJson,
  stringifyJson,
  toOffsetDateTime,
} from './form';

type Validator = (rule: object, value: unknown) => Promise<void>;

const validate = (value: unknown) => {
  const rule = jsonRule('invalid') as () => { validator: Validator };
  return rule().validator({}, value);
};

describe('jsonRule', () => {
  it('accepts empty values and JSON objects / arrays', async () => {
    await expect(validate(undefined)).resolves.toBeUndefined();
    await expect(validate('')).resolves.toBeUndefined();
    await expect(validate('{"a":1}')).resolves.toBeUndefined();
    await expect(validate('[1, 2]')).resolves.toBeUndefined();
  });

  it('rejects invalid JSON and scalars', async () => {
    await expect(validate('{a:1}')).rejects.toThrow('invalid');
    await expect(validate('1')).rejects.toThrow('invalid');
    await expect(validate('null')).rejects.toThrow('invalid');
  });
});

describe('stringifyJson / parseJson', () => {
  it('round-trips objects through formatted text', () => {
    const text = stringifyJson({ a: [1, 2] });
    expect(text).toBe('{\n  "a": [\n    1,\n    2\n  ]\n}');
    expect(parseJson(text)).toEqual({ a: [1, 2] });
  });

  it('handles empty and invalid input', () => {
    expect(stringifyJson(null)).toBe('');
    expect(stringifyJson('raw')).toBe('raw');
    expect(parseJson('  ')).toBeNull();
    expect(parseJson(undefined)).toBeNull();
    expect(parseJson('{bad')).toBe('{bad');
  });
});

describe('clearFieldErrors', () => {
  it('clears errors of the changed top-level fields', () => {
    const setFields = vi.fn();
    clearFieldErrors({ setFields }, { name: 'x', meta: { a: 1 } });
    expect(setFields).toHaveBeenCalledWith([
      { name: 'name', errors: [] },
      { name: 'meta', errors: [] },
    ]);
  });
});

describe('toOffsetDateTime', () => {
  it('adds the browser offset to local date times', () => {
    const result = toOffsetDateTime('2026-11-16 18:30:00');

    expect(result).toMatch(/^2026-11-16T18:30:00[+-]\d{2}:\d{2}$/);
    expect(new Date(result as string).getTime()).toBe(
      new Date(2026, 10, 16, 18, 30).getTime(),
    );
    expect(toOffsetDateTime(dayjs('2026-11-16 18:30:00'))).toBe(result);
  });

  it('keeps the instant of values that already carry an offset', () => {
    const result = toOffsetDateTime('2026-11-16T10:30:00Z');

    expect(new Date(result as string).toISOString()).toBe(
      '2026-11-16T10:30:00.000Z',
    );
  });

  it('turns empty values into null and leaves unparsable values alone', () => {
    expect(toOffsetDateTime(undefined)).toBeNull();
    expect(toOffsetDateTime('')).toBeNull();
    expect(toOffsetDateTime('not a date')).toBe('not a date');
  });
});
