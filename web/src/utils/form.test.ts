import { describe, expect, it, vi } from 'vitest';
import { clearFieldErrors, jsonRule, parseJson, stringifyJson } from './form';

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
