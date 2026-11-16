/**
 * 请求错误解析。umi request 基于 axios，失败时抛出 AxiosError，
 * 这里只做结构判断，避免业务代码直接依赖 axios。
 */
export interface HttpError {
  status: number;
  body?: API.ErrorBody;
}

export interface FieldError {
  name: string;
  errors: string[];
}

const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null;

/** 是否为请求层错误（有响应的 HTTP 错误或无响应的网络错误），用于区分代码缺陷 */
export function isRequestError(error: unknown): boolean {
  return isObject(error) && ('response' in error || 'request' in error);
}

/** 从请求错误中取出 HTTP 状态码与 Laravel 错误体；不是 HTTP 错误时返回 undefined */
export function toHttpError(error: unknown): HttpError | undefined {
  if (!isObject(error) || !isObject(error.response)) return undefined;
  const { status, data } = error.response;
  if (typeof status !== 'number') return undefined;
  const body =
    isObject(data) && typeof data.message === 'string'
      ? (data as unknown as API.ErrorBody)
      : undefined;
  return { status, body };
}

/**
 * 422 校验错误 → antd `form.setFields` 需要的结构。
 * 嵌套字段（如 `role_ids.0`）归到顶层字段 `role_ids` 上。
 */
export function getFieldErrors(error: unknown): FieldError[] {
  const httpError = toHttpError(error);
  if (httpError?.status !== 422 || !httpError.body?.errors) return [];

  const merged = new Map<string, string[]>();
  for (const [key, messages] of Object.entries(httpError.body.errors)) {
    const name = key.split('.')[0];
    merged.set(name, [...(merged.get(name) ?? []), ...messages]);
  }
  return Array.from(merged, ([name, errors]) => ({ name, errors }));
}
