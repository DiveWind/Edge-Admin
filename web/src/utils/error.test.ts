import { describe, expect, it } from 'vitest';
import { getFieldErrors, toHttpError } from './error';

const axiosLikeError = (status: number, data: unknown) => ({
  name: 'AxiosError',
  message: 'Request failed',
  response: { status, data },
});

describe('toHttpError', () => {
  it('extracts status and Laravel error body', () => {
    expect(toHttpError(axiosLikeError(403, { message: 'Forbidden' }))).toEqual(
      { status: 403, body: { message: 'Forbidden' } },
    );
  });

  it('ignores bodies that are not Laravel errors', () => {
    expect(toHttpError(axiosLikeError(500, '<html>'))).toEqual({
      status: 500,
      body: undefined,
    });
  });

  it('returns undefined for non-HTTP errors', () => {
    expect(toHttpError(new Error('boom'))).toBeUndefined();
    expect(toHttpError(undefined)).toBeUndefined();
  });
});

describe('getFieldErrors', () => {
  it('maps 422 errors to form fields and merges nested keys', () => {
    const error = axiosLikeError(422, {
      message: 'The given data was invalid.',
      errors: {
        username: ['The username has already been taken.'],
        'role_ids.0': ['The selected role is invalid.'],
        'role_ids.1': ['The selected role is disabled.'],
      },
    });
    expect(getFieldErrors(error)).toEqual([
      { name: 'username', errors: ['The username has already been taken.'] },
      {
        name: 'role_ids',
        errors: ['The selected role is invalid.', 'The selected role is disabled.'],
      },
    ]);
  });

  it('returns an empty list for other errors', () => {
    expect(getFieldErrors(axiosLikeError(403, { message: 'No' }))).toEqual([]);
    expect(getFieldErrors(new Error('boom'))).toEqual([]);
  });
});
