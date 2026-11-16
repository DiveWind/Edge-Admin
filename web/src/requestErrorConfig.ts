import type { RequestConfig, RequestOptions } from '@umijs/max';
import { getLocale } from '@umijs/max';
import { getMessageApi } from '@/components/FeedbackBridge';
import { clearToken, getToken, redirectToLogin } from '@/utils/auth';
import { isRequestError, toHttpError } from '@/utils/error';
import { t } from '@/utils/i18n';

/**
 * 适配 Laravel 标准响应的请求配置（请求头带 Bearer Token 与当前界面语言 Accept-Language）
 * - 成功：HTTP 2xx，响应体为 API Resource（`{data}`）或 204 无内容
 * - 失败：HTTP 状态码 + `{message, errors?}`
 *
 * 单个请求可传 `skipErrorHandler: true` 自行处理错误（错误会原样抛出）。
 * 表单 422 字段回填见 `@/utils/form#submitForm`。
 * @doc https://umijs.org/docs/max/request#配置
 */
export const errorConfig: RequestConfig = {
  errorConfig: {
    errorHandler: (error, opts) => {
      if (opts?.skipErrorHandler) throw error;
      const message = getMessageApi();

      const httpError = toHttpError(error);
      if (httpError) {
        const serverMessage = httpError.body?.message;
        switch (httpError.status) {
          case 401:
            clearToken();
            // 并发请求同时 401 时只提示一次
            message?.warning({
              key: 'unauthorized',
              content: t('app.request.401'),
            });
            redirectToLogin();
            break;
          case 403:
            message?.error(serverMessage || t('app.request.403'));
            break;
          case 422:
            message?.error(serverMessage || t('app.request.422'));
            break;
          case 429:
            message?.error(serverMessage || t('app.request.429'));
            break;
          default:
            message?.error(
              serverMessage ||
                t('app.request.status', { status: httpError.status }),
            );
        }
        return;
      }

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        message?.error(t('app.request.offline'));
      } else if (isRequestError(error)) {
        message?.error(t('app.request.noResponse'));
      } else {
        message?.error(t('app.request.unknown'));
      }
    },
  },

  requestInterceptors: [
    (config: RequestOptions) => {
      const token = getToken();
      return {
        ...config,
        headers: {
          ...config.headers,
          Accept: 'application/json',
          // 后端按此返回对应语言的 message / errors；跟随界面语言而不是浏览器语言
          'Accept-Language': getLocale(),
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
      };
    },
  ],
};
