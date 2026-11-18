import type { PromptProps } from '@vben/common-ui';

import { useAccessStore, useUserStore } from '@vben/stores';

import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import * as api from '#/api';

import { SESSION_MARKER, TWO_FACTOR_PATH, useAuthStore } from '../auth';

const router = vi.hoisted(() => ({
  currentRoute: { value: { fullPath: '/', path: '/', query: {} } } as {
    value: { fullPath: string; path: string; query: Record<string, string> };
  },
  push: vi.fn(),
  replace: vi.fn(),
}));

const prompt = vi.hoisted(() => vi.fn());
const ui = vi.hoisted(() => ({
  message: { error: vi.fn(), success: vi.fn(), warning: vi.fn() },
  notification: { success: vi.fn() },
}));

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<typeof import('vue-router')>()),
  useRouter: () => router,
}));

vi.mock('@vben/common-ui', () => ({ prompt, VbenInputPassword: {} }));

vi.mock('@vben/stores', async (importOriginal) => {
  const stores = await importOriginal<typeof import('@vben/stores')>();
  return {
    ...stores,
    resetAllStores: () => {
      stores.useAccessStore().$reset();
      stores.useUserStore().$reset();
    },
  };
});

vi.mock('antdv-next', () => ui);

vi.mock('#/locales', () => ({ $t: (key: string) => key }));

vi.mock('#/api', () => ({
  confirmPasswordApi: vi.fn(),
  getCsrfCookieApi: vi.fn(),
  getUserInfoApi: vi.fn(),
  loginApi: vi.fn(),
  logoutApi: vi.fn(),
  twoFactorChallengeApi: vi.fn(),
}));

// 只用到纯函数（isErrorStatus、getErrorMessage），不创建真正的请求客户端
vi.mock('#/api/request', () => import('#/api/client'));

const userInfo = {
  avatar: null,
  desc: '',
  homePath: '/analytics',
  realName: '管理员',
  roles: [],
  userId: '1',
  username: 'admin',
};

const error = (code: number, extra = {}) => ({
  code,
  data: null,
  message: `error ${code}`,
  ...extra,
});

function setRoute(fullPath: string, query: Record<string, string> = {}) {
  router.currentRoute.value = {
    fullPath,
    path: fullPath.split('?')[0] ?? fullPath,
    query,
  };
}

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.clearAllMocks();
    setRoute('/auth/login');
    vi.mocked(api.getCsrfCookieApi).mockResolvedValue(null);
    vi.mocked(api.getUserInfoApi).mockResolvedValue(userInfo as any);
    vi.mocked(api.logoutApi).mockResolvedValue(null);
  });

  describe('login', () => {
    it('gets the CSRF cookie, logs in with only the three fields, then loads the user', async () => {
      const calls: string[] = [];
      vi.mocked(api.getCsrfCookieApi).mockImplementation(async () => {
        calls.push('csrf');
        return null;
      });
      vi.mocked(api.loginApi).mockImplementation(async () => {
        calls.push('login');
        return { two_factor: false };
      });
      vi.mocked(api.getUserInfoApi).mockImplementation(async () => {
        calls.push('info');
        return userInfo as any;
      });
      const store = useAuthStore();

      const result = await store.authLogin({
        captcha: true,
        password: 'password',
        remember: true,
        username: 'admin',
      });

      expect(calls).toEqual(['csrf', 'login', 'info']);
      expect(api.loginApi).toHaveBeenCalledWith({
        password: 'password',
        remember: true,
        username: 'admin',
      });
      expect(result).toEqual({ twoFactor: false, userInfo });
      expect(useAccessStore().accessToken).toBe(SESSION_MARKER);
      expect(useUserStore().userInfo).toEqual(userInfo);
      expect(router.push).toHaveBeenCalledWith('/analytics');
      expect(ui.notification.success).toHaveBeenCalled();
      expect(store.loginLoading).toBe(false);
    });

    it('goes back to the redirect page after logging in', async () => {
      setRoute('/auth/login?redirect=%252Fprofile', {
        redirect: encodeURIComponent('/profile'),
      });
      vi.mocked(api.loginApi).mockResolvedValue({ two_factor: false });

      await useAuthStore().authLogin({ password: 'p', username: 'u' });

      expect(api.loginApi).toHaveBeenCalledWith({
        password: 'p',
        remember: false,
        username: 'u',
      });
      expect(router.push).toHaveBeenCalledWith('/profile');
    });

    it('moves to the two-factor page when a challenge is required', async () => {
      setRoute('/auth/login', { redirect: 'x' });
      vi.mocked(api.loginApi).mockResolvedValue({ two_factor: true });

      const result = await useAuthStore().authLogin({
        password: 'p',
        username: 'u',
      });

      expect(result).toEqual({ twoFactor: true, userInfo: null });
      expect(router.push).toHaveBeenCalledWith({
        path: TWO_FACTOR_PATH,
        query: { redirect: 'x' },
      });
      expect(api.getUserInfoApi).not.toHaveBeenCalled();
      expect(useAccessStore().accessToken).toBeNull();
    });

    it('enters the system when already logged in (409)', async () => {
      vi.mocked(api.loginApi).mockRejectedValue(error(409));

      await useAuthStore().authLogin({ password: 'p', username: 'u' });

      expect(api.getUserInfoApi).toHaveBeenCalledTimes(1);
      expect(useAccessStore().accessToken).toBe(SESSION_MARKER);
      expect(router.push).toHaveBeenCalledWith('/analytics');
    });

    it('rethrows validation errors for the form and stays logged out', async () => {
      const failure = error(422, { errors: { username: ['账号已停用'] } });
      vi.mocked(api.loginApi).mockRejectedValue(failure);
      const store = useAuthStore();

      await expect(
        store.authLogin({ password: 'p', username: 'disabled' }),
      ).rejects.toBe(failure);

      expect(api.getUserInfoApi).not.toHaveBeenCalled();
      expect(useAccessStore().accessToken).toBeNull();
      expect(store.loginLoading).toBe(false);
    });
  });

  describe('two-factor challenge', () => {
    it('completes the login with a recovery code', async () => {
      setRoute(TWO_FACTOR_PATH);
      vi.mocked(api.twoFactorChallengeApi).mockResolvedValue(null);

      await useAuthStore().authTwoFactor({ recovery_code: 'abc-def' });

      expect(api.twoFactorChallengeApi).toHaveBeenCalledWith({
        recovery_code: 'abc-def',
      });
      expect(useAccessStore().accessToken).toBe(SESSION_MARKER);
      expect(router.push).toHaveBeenCalledWith('/analytics');
    });

    it('rethrows an invalid code', async () => {
      const failure = error(422, { errors: { code: ['动态验证码错误。'] } });
      vi.mocked(api.twoFactorChallengeApi).mockRejectedValue(failure);

      await expect(
        useAuthStore().authTwoFactor({ code: '000000' }),
      ).rejects.toBe(failure);
      expect(useAccessStore().accessToken).toBeNull();
    });
  });

  describe('logout', () => {
    it('clears the marker before calling the API, then returns to the login page', async () => {
      setRoute('/profile');
      const accessStore = useAccessStore();
      accessStore.setAccessToken(SESSION_MARKER);
      let markerDuringLogout: null | string = 'unset';
      vi.mocked(api.logoutApi).mockImplementation(async () => {
        markerDuringLogout = accessStore.accessToken;
        return null;
      });

      await useAuthStore().logout();

      expect(markerDuringLogout).toBeNull();
      expect(router.replace).toHaveBeenCalledWith({
        path: '/auth/login',
        query: { redirect: encodeURIComponent('/profile') },
      });
    });

    it('still clears the local state when the API fails', async () => {
      useAccessStore().setAccessToken(SESSION_MARKER);
      useUserStore().setUserInfo(userInfo as any);
      vi.mocked(api.logoutApi).mockRejectedValue(error(401));

      await useAuthStore().logout(false);

      expect(useAccessStore().accessToken).toBeNull();
      expect(useUserStore().userInfo).toBeNull();
      expect(router.replace).toHaveBeenCalledWith({
        path: '/auth/login',
        query: {},
      });
    });
  });

  describe('401 handling', () => {
    it('does nothing without the logged-in marker', async () => {
      await useAuthStore().handleUnauthorized();

      expect(router.replace).not.toHaveBeenCalled();
    });

    it('only clears the marker during start-up (the guard redirects)', async () => {
      useAccessStore().setAccessToken(SESSION_MARKER);

      await useAuthStore().handleUnauthorized();

      expect(useAccessStore().accessToken).toBeNull();
      expect(router.replace).not.toHaveBeenCalled();
    });

    it('returns to the login page once inside the system', async () => {
      setRoute('/analytics');
      const accessStore = useAccessStore();
      accessStore.setAccessToken(SESSION_MARKER);
      accessStore.setIsAccessChecked(true);

      await useAuthStore().handleUnauthorized();

      expect(accessStore.accessToken).toBeNull();
      expect(api.logoutApi).not.toHaveBeenCalled();
      expect(router.replace).toHaveBeenCalledWith({
        path: '/auth/login',
        query: { redirect: encodeURIComponent('/analytics') },
      });
    });
  });

  describe('password confirmation (423)', () => {
    type BeforeClose = NonNullable<PromptProps<string>['beforeClose']>;

    function answerPrompt(...answers: Parameters<BeforeClose>[0][]) {
      const results: unknown[] = [];
      prompt.mockImplementation(async (options: PromptProps<string>) => {
        for (const answer of answers) {
          const keepOpen = (await options.beforeClose?.(answer)) === false;
          results.push(keepOpen ? 'open' : 'closed');
          if (!keepOpen) {
            if (answer.isConfirm) {
              return answer.value;
            }
            throw new Error('dialog cancelled');
          }
        }
        throw new Error('dialog cancelled');
      });
      return results;
    }

    it('confirms the password and resolves true', async () => {
      vi.mocked(api.confirmPasswordApi).mockResolvedValue(null);
      answerPrompt({ isConfirm: true, value: 'password' });

      await expect(useAuthStore().confirmPassword()).resolves.toBe(true);
      expect(api.confirmPasswordApi).toHaveBeenCalledWith('password');
    });

    it('keeps the dialog open on a wrong password and shows the reason', async () => {
      vi.mocked(api.confirmPasswordApi)
        .mockRejectedValueOnce(
          error(422, { errors: { password: ['密码错误。'] } }),
        )
        .mockResolvedValueOnce(null);
      const results = answerPrompt(
        { isConfirm: true, value: 'wrong' },
        { isConfirm: true, value: 'password' },
      );

      await expect(useAuthStore().confirmPassword()).resolves.toBe(true);
      expect(results).toEqual(['open', 'closed']);
      expect(ui.message.error).toHaveBeenCalledWith('密码错误。');
    });

    it('resolves false when cancelled', async () => {
      answerPrompt({ isConfirm: false, value: undefined });

      await expect(useAuthStore().confirmPassword()).resolves.toBe(false);
      expect(api.confirmPasswordApi).not.toHaveBeenCalled();
    });
  });
});
