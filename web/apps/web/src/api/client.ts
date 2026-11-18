/**
 * 请求客户端工厂：Sanctum 会话 Cookie + Vben 响应格式（契约见 docs/backend-design.md §6.1、§6.2、§7）。
 *
 * 只依赖 @vben/request，不依赖 store 与界面（由 request.ts 注入），便于单元测试。
 *
 * - 请求：携带 Cookie（withCredentials），从 Cookie `XSRF-TOKEN` 读取令牌放入请求头 `X-XSRF-TOKEN`（withXSRFToken，
 *   每次请求时读取，登录、登出后令牌轮换也无需处理）；`Accept: application/json`；没有 Authorization 头与刷新令牌。
 * - 响应：`{ code, data, message }`，`code === 0` 为成功，返回 `data`。
 * - 认证相关的状态码：
 *   - 419 CSRF 令牌失效：重新获取 CSRF Cookie 后重试一次；
 *   - 423 需要确认密码：弹出确认密码框，确认后重试一次；取消时以 CanceledError 结束（不提示）；
 *   - 401 未登录、会话失效或账号已停用：交给 onUnauthorized（清除登录标记、回登录页），并提示 message；
 *   - 409、422、429 等：提示 message；422 的 `errors` 由调用方回填到表单（见 getValidationErrors）。
 */
import type {
  InternalAxiosRequestConfig,
  RequestClientConfig,
  RequestClientOptions,
} from '@vben/request';

import {
  CanceledError,
  defaultResponseInterceptor,
  errorMessageResponseInterceptor,
  RequestClient,
} from '@vben/request';

/** 获取 CSRF Cookie 的接口（相对后台入口前缀，§6.2） */
export const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

/** 请求的额外选项 */
export interface RequestOptions {
  /**
   * 不自动提示错误：`true` 为全部不提示；数组为不提示的 HTTP 状态码（由调用方自行处理，如登录时的 409）
   */
  silentError?: boolean | number[];
}

/** 带额外选项的请求配置（先赋给变量再传给 requestClient，避免对象字面量的多余属性检查） */
export type AppRequestConfig = RequestClientConfig & RequestOptions;

/** 认证相关状态码的处理（由 request.ts 注入，测试中替换为 mock） */
export interface SessionHandlers {
  /** 423：让用户确认密码；确认成功返回 true，取消返回 false */
  confirmPassword: () => Promise<boolean>;
  /** 401：会话失效或账号已停用 */
  onUnauthorized: () => Promise<void> | void;
  /** 419：重新获取 CSRF Cookie */
  refreshCsrfToken: () => Promise<unknown>;
  /** 显示错误提示 */
  showError: (message: string) => void;
}

/** 失败响应体（RequestClient 抛给调用方的就是它） */
export interface ErrorResponseBody {
  code: number;
  data: null;
  errors?: Record<string, string[]>;
  message: string;
}

/** 重试标记：每种状态码只重试一次 */
interface RetryFlags {
  /** 已因 419 重试过 */
  _csrfRetried?: boolean;
  /** 已因 423 确认密码后重试过 */
  _passwordRetried?: boolean;
}

type RetryConfig = AppRequestConfig & InternalAxiosRequestConfig & RetryFlags;

/** 判断错误（RequestClient 抛出的响应体）是否为某个 HTTP 状态码 */
export function isErrorStatus(
  error: unknown,
  status: number,
): error is ErrorResponseBody {
  return (
    typeof error === 'object' &&
    error !== null &&
    (error as Partial<ErrorResponseBody>).code === status
  );
}

/** 422 的字段错误（`{ 字段: [错误] }`）；不是 422 时返回 undefined */
export function getValidationErrors(
  error: unknown,
): Record<string, string[]> | undefined {
  return isErrorStatus(error, 422) ? error.errors : undefined;
}

/** 第一条字段错误，没有时取 message */
export function getErrorMessage(error: unknown): string | undefined {
  const errors = getValidationErrors(error);
  const first = errors ? Object.values(errors)[0]?.[0] : undefined;
  if (first) {
    return first;
  }
  const message = (error as Partial<ErrorResponseBody> | undefined)?.message;
  return typeof message === 'string' && message !== '' ? message : undefined;
}

function isSilent(config: AppRequestConfig | undefined, status?: number) {
  const silent = config?.silentError;
  if (silent === true) {
    return true;
  }
  return (
    Array.isArray(silent) && status !== undefined && silent.includes(status)
  );
}

/** 同一时刻只执行一次（并发的 419 / 423 共用一次刷新或确认） */
function shared<T>(fn: () => Promise<T>): () => Promise<T> {
  let pending: null | Promise<T> = null;
  return () => {
    pending ??= fn().finally(() => {
      pending = null;
    });
    return pending;
  };
}

export function createRequestClient(
  baseURL: string,
  handlers: SessionHandlers,
  options: RequestClientOptions = {},
) {
  const client = new RequestClient({
    ...options,
    baseURL,
    headers: {
      Accept: 'application/json',
      ...options.headers,
    },
    withCredentials: true,
    withXSRFToken: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
  });

  const refreshCsrfToken = shared(handlers.refreshCsrfToken);
  const confirmPassword = shared(handlers.confirmPassword);

  // 已处理过的错误：重试的请求失败时，错误会依次经过重试请求与原请求的拦截器，只处理、提示一次
  const handled = new WeakSet<object>();
  const notified = new WeakSet<object>();

  // 处理返回的响应数据格式（Vben 默认）
  client.addResponseInterceptor(
    defaultResponseInterceptor({
      codeField: 'code',
      dataField: 'data',
      successCode: 0,
    }),
  );

  // 认证相关的状态码
  client.addResponseInterceptor({
    rejected: async (error) => {
      if (typeof error !== 'object' || error === null || handled.has(error)) {
        throw error;
      }
      handled.add(error);

      const config = error.config as RetryConfig | undefined;
      const status: number | undefined = error.response?.status;

      if (status === 419 && config && !config._csrfRetried) {
        await refreshCsrfToken();
        const retry: RetryConfig = { ...config, _csrfRetried: true };
        return client.instance.request(retry);
      }

      if (status === 423 && config && !config._passwordRetried) {
        if (!(await confirmPassword())) {
          throw new CanceledError('password confirmation cancelled');
        }
        const retry: RetryConfig = { ...config, _passwordRetried: true };
        return client.instance.request(retry);
      }

      if (status === 401) {
        await handlers.onUnauthorized();
      }

      throw error;
    },
  });

  // 错误提示：优先使用接口返回的 message，没有时按状态码给出通用文案（网络错误、超时等）
  client.addResponseInterceptor(
    errorMessageResponseInterceptor((msg: string, error: any) => {
      if (notified.has(error)) {
        return;
      }
      notified.add(error);

      const status: number | undefined = error?.response?.status;
      if (isSilent(error?.config, status)) {
        return;
      }
      const responseMessage = error?.response?.data?.message;
      handlers.showError(
        typeof responseMessage === 'string' && responseMessage !== ''
          ? responseMessage
          : msg,
      );
    }),
  );

  return client;
}
