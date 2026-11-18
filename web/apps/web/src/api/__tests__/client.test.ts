import type { AppRequestConfig, SessionHandlers } from '../client';

import { isCancel } from '@vben/request';

import MockAdapter from 'axios-mock-adapter';
import { describe, expect, it, vi } from 'vitest';

import {
  createRequestClient,
  getErrorMessage,
  getValidationErrors,
  isErrorStatus,
} from '../client';

const ok = (data: unknown) => ({ code: 0, data, message: 'ok' });
const fail = (code: number, message: string, extra = {}) => ({
  code,
  data: null,
  message,
  ...extra,
});

function setup(overrides: Partial<SessionHandlers> = {}) {
  const handlers = {
    confirmPassword: vi.fn(async () => true),
    onUnauthorized: vi.fn(),
    refreshCsrfToken: vi.fn(async () => null),
    showError: vi.fn(),
    ...overrides,
  };
  const client = createRequestClient('/api/admin', handlers, {
    responseReturn: 'data',
  });
  const mock = new MockAdapter(client.instance);
  return { client, handlers, mock };
}

describe('request client', () => {
  it('sends the session cookie and the XSRF token, without Authorization', async () => {
    const { client, mock } = setup();
    mock.onGet('/user/info').reply(200, ok({ userId: '1' }));

    await expect(client.get('/user/info')).resolves.toEqual({ userId: '1' });

    const [request] = mock.history.get;
    expect(request?.withCredentials).toBe(true);
    expect(request?.withXSRFToken).toBe(true);
    expect(request?.xsrfCookieName).toBe('XSRF-TOKEN');
    expect(request?.xsrfHeaderName).toBe('X-XSRF-TOKEN');
    expect(request?.headers?.Accept).toBe('application/json');
    expect(request?.headers?.Authorization).toBeUndefined();
  });

  it('rejects a 200 response whose code is not 0', async () => {
    const { client, handlers, mock } = setup();
    mock.onGet('/user/info').reply(200, fail(1, 'failed'));

    await expect(client.get('/user/info')).rejects.toMatchObject({ code: 1 });
    expect(handlers.showError).toHaveBeenCalledWith('failed');
  });

  describe('419 CSRF token mismatch', () => {
    it('refreshes the CSRF cookie and retries once', async () => {
      const { client, handlers, mock } = setup();
      mock
        .onPut('/user/profile-information')
        .replyOnce(419, fail(419, '页面已过期'))
        .onPut('/user/profile-information')
        .replyOnce(200, ok(null));

      await expect(
        client.put('/user/profile-information', { name: 'A' }),
      ).resolves.toBeNull();

      expect(handlers.refreshCsrfToken).toHaveBeenCalledTimes(1);
      expect(mock.history.put).toHaveLength(2);
      expect(JSON.parse(mock.history.put[1]?.data)).toEqual({ name: 'A' });
      expect(handlers.showError).not.toHaveBeenCalled();
    });

    it('gives up after one retry and shows the message once', async () => {
      const { client, handlers, mock } = setup();
      mock.onPost('/logout').reply(419, fail(419, '页面已过期'));

      await expect(client.post('/logout')).rejects.toMatchObject({
        code: 419,
      });

      expect(handlers.refreshCsrfToken).toHaveBeenCalledTimes(1);
      expect(mock.history.post).toHaveLength(2);
      expect(handlers.showError).toHaveBeenCalledTimes(1);
      expect(handlers.showError).toHaveBeenCalledWith('页面已过期');
    });

    it('shows one message when refreshing the CSRF cookie fails', async () => {
      const ctx = setup({
        refreshCsrfToken: () => ctx.client.instance.get('/sanctum/csrf-cookie'),
      });
      ctx.mock.onPost('/logout').reply(419, fail(419, '页面已过期'));
      ctx.mock
        .onGet('/sanctum/csrf-cookie')
        .reply(500, fail(500, '服务器错误'));

      await expect(ctx.client.post('/logout')).rejects.toBeDefined();
      expect(ctx.mock.history.post).toHaveLength(1);
      expect(ctx.handlers.showError).toHaveBeenCalledTimes(1);
      expect(ctx.handlers.showError).toHaveBeenCalledWith('服务器错误');
    });

    it('shares one refresh between concurrent requests', async () => {
      const { client, handlers, mock } = setup();
      mock
        .onPost('/a')
        .replyOnce(419, fail(419, 'expired'))
        .onPost('/a')
        .replyOnce(200, ok('a'));
      mock
        .onPost('/b')
        .replyOnce(419, fail(419, 'expired'))
        .onPost('/b')
        .replyOnce(200, ok('b'));

      await expect(
        Promise.all([client.post('/a'), client.post('/b')]),
      ).resolves.toEqual(['a', 'b']);
      expect(handlers.refreshCsrfToken).toHaveBeenCalledTimes(1);
    });
  });

  describe('423 password confirmation required', () => {
    it('asks for the password and retries the request', async () => {
      const { client, handlers, mock } = setup();
      mock
        .onGet('/user/two-factor-qr-code')
        .replyOnce(423, fail(423, '请先确认密码'))
        .onGet('/user/two-factor-qr-code')
        .replyOnce(200, ok({ svg: '<svg/>', url: 'otpauth://' }));

      await expect(client.get('/user/two-factor-qr-code')).resolves.toEqual({
        svg: '<svg/>',
        url: 'otpauth://',
      });
      expect(handlers.confirmPassword).toHaveBeenCalledTimes(1);
      expect(mock.history.get).toHaveLength(2);
      expect(handlers.showError).not.toHaveBeenCalled();
    });

    it('ends with a cancellation (no message) when the user cancels', async () => {
      const { client, handlers, mock } = setup({
        confirmPassword: vi.fn(async () => false),
      });
      mock
        .onDelete('/user/two-factor-authentication')
        .reply(423, fail(423, '请先确认密码'));

      const error = await client
        .delete('/user/two-factor-authentication')
        .catch((error_: unknown) => error_);

      expect(isCancel(error)).toBe(true);
      expect(mock.history.delete).toHaveLength(1);
      expect(handlers.showError).not.toHaveBeenCalled();
    });

    it('does not ask again when the retried request is still locked', async () => {
      const { client, handlers, mock } = setup();
      mock
        .onPost('/user/two-factor-recovery-codes')
        .reply(423, fail(423, '请先确认密码'));

      await expect(
        client.post('/user/two-factor-recovery-codes'),
      ).rejects.toMatchObject({ code: 423 });
      expect(handlers.confirmPassword).toHaveBeenCalledTimes(1);
      expect(mock.history.post).toHaveLength(2);
      expect(handlers.showError).toHaveBeenCalledTimes(1);
    });
  });

  describe('409 already authenticated', () => {
    it('is left to the caller when silenced', async () => {
      const { client, handlers, mock } = setup();
      mock.onPost('/login').reply(409, fail(409, '已登录，请勿重复操作'));
      const config: AppRequestConfig = { silentError: [409] };

      const error = await client
        .post('/login', {}, config)
        .catch((error_: unknown) => error_);

      expect(isErrorStatus(error, 409)).toBe(true);
      expect(handlers.showError).not.toHaveBeenCalled();
      expect(handlers.onUnauthorized).not.toHaveBeenCalled();
    });

    it('shows the message otherwise', async () => {
      const { client, handlers, mock } = setup();
      mock.onPost('/forgot-password').reply(409, fail(409, '已登录'));

      await expect(client.post('/forgot-password')).rejects.toMatchObject({
        code: 409,
      });
      expect(handlers.showError).toHaveBeenCalledWith('已登录');
    });
  });

  describe('401 unauthenticated', () => {
    it('notifies the session handler and shows the message', async () => {
      const { client, handlers, mock } = setup();
      mock.onGet('/user/info').reply(401, fail(401, '账号已停用'));

      await expect(client.get('/user/info')).rejects.toMatchObject({
        code: 401,
        message: '账号已停用',
      });
      expect(handlers.onUnauthorized).toHaveBeenCalledTimes(1);
      expect(handlers.showError).toHaveBeenCalledWith('账号已停用');
      expect(handlers.refreshCsrfToken).not.toHaveBeenCalled();
    });

    it('is handled once when a retried request becomes unauthenticated', async () => {
      const { client, handlers, mock } = setup();
      mock
        .onPost('/user/password')
        .replyOnce(419, fail(419, 'expired'))
        .onPost('/user/password')
        .replyOnce(401, fail(401, '未登录或登录已过期'));

      await expect(client.post('/user/password')).rejects.toMatchObject({
        code: 401,
      });
      expect(handlers.onUnauthorized).toHaveBeenCalledTimes(1);
      expect(handlers.showError).toHaveBeenCalledTimes(1);
    });
  });

  describe('422 validation errors', () => {
    it('shows the message and exposes the field errors', async () => {
      const { client, handlers, mock } = setup();
      const errors = { username: ['用户名或密码错误。'] };
      mock.onPost('/login').reply(422, fail(422, '数据校验失败', { errors }));

      const error = await client
        .post('/login')
        .catch((error_: unknown) => error_);

      expect(handlers.showError).toHaveBeenCalledWith('数据校验失败');
      expect(getValidationErrors(error)).toEqual(errors);
      expect(getErrorMessage(error)).toBe('用户名或密码错误。');
    });
  });

  it('shows a 429 message as returned', async () => {
    const { client, handlers, mock } = setup();
    mock
      .onPost('/login')
      .reply(429, fail(429, '登录尝试次数过多，请 60 秒后重试。'));

    await expect(client.post('/login')).rejects.toMatchObject({ code: 429 });
    expect(handlers.showError).toHaveBeenCalledWith(
      '登录尝试次数过多，请 60 秒后重试。',
    );
  });

  it('silences every error with silentError: true', async () => {
    const { client, handlers, mock } = setup();
    mock.onPost('/logout').networkError();
    const config: AppRequestConfig = { silentError: true };

    await expect(client.post('/logout', undefined, config)).rejects.toThrow(
      'Network Error',
    );
    expect(handlers.showError).not.toHaveBeenCalled();
  });
});
