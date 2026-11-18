/**
 * 请求客户端：拦截器见 client.ts，这里注入认证相关状态码的处理（store 与界面）。
 */
import { useAppConfig } from '@vben/hooks';
import { preferences } from '@vben/preferences';

import { message } from 'antdv-next';

import { useAuthStore } from '#/store';

import { createRequestClient, CSRF_COOKIE_URL } from './client';

export type { AppRequestConfig, ErrorResponseBody } from './client';
export { getErrorMessage, getValidationErrors, isErrorStatus } from './client';

const { apiURL } = useAppConfig(import.meta.env, import.meta.env.PROD);

export const requestClient = createRequestClient(
  apiURL,
  {
    confirmPassword: () => useAuthStore().confirmPassword(),
    onUnauthorized: () => useAuthStore().handleUnauthorized(),
    // 直接用 axios 实例：失败时抛出的是已提示过的原始错误，不会重复提示
    refreshCsrfToken: () => requestClient.instance.get(CSRF_COOKIE_URL),
    showError: (msg) => {
      message.error(msg);
    },
  },
  { responseReturn: 'data' },
);

// 请求头：界面语言
requestClient.addRequestInterceptor({
  fulfilled: (config) => {
    config.headers['Accept-Language'] = preferences.app.locale;
    return config;
  },
});
