import type { FormInstance, FormRule } from 'antd';
import dayjs from 'dayjs';
import { getFieldErrors, isRequestError } from './error';

/**
 * ModalForm / DrawerForm 的 onFinish 包装：成功返回 true（关闭弹窗），
 * 失败时把 422 的 `errors` 回填到表单字段并返回 false。
 * 错误提示（message）由全局 errorHandler 负责，这里不重复提示。
 *
 * @example
 * onFinish={(values) => submitForm(formRef.current, () => createUser(values))}
 */
export async function submitForm(
  form: Pick<FormInstance, 'setFields'> | null | undefined,
  action: () => Promise<unknown>,
): Promise<boolean> {
  try {
    await action();
    return true;
  } catch (error) {
    // 非请求错误（代码缺陷）继续抛出，不吞掉
    if (!isRequestError(error)) throw error;
    const fieldErrors = getFieldErrors(error);
    if (fieldErrors.length) form?.setFields(fieldErrors);
    return false;
  }
}

/**
 * 清除被修改字段上的错误（用在 `onValuesChange`）。
 * 422 回填的错误只有在字段重新校验时才会消失，而没有前端 `rules` 的字段输入时不会校验，错误会一直留着。
 *
 * @example
 * onValuesChange={(changed) => clearFieldErrors(formRef.current, changed)}
 */
export function clearFieldErrors(
  form: Pick<FormInstance, 'setFields'> | null | undefined,
  changedValues: object,
): void {
  const fields = Object.keys(changedValues).map((name) => ({
    name,
    errors: [],
  }));
  if (fields.length) form?.setFields(fields);
}

/** 确认字段校验：值必须与 `field` 一致（如 password_confirmation） */
export const sameAsRule =
  (field: string, message: string): FormRule =>
  ({ getFieldValue }) => ({
    validator: (_, value) =>
      !value || value === getFieldValue(field)
        ? Promise.resolve()
        : Promise.reject(new Error(message)),
  });

/** JSON 文本字段校验：为空时通过（必填另用 required），否则必须能被 JSON.parse 且为对象或数组 */
export const jsonRule =
  (message: string): FormRule =>
  () => ({
    validator: (_, value: unknown) => {
      if (value === undefined || value === null || value === '') {
        return Promise.resolve();
      }
      if (typeof value !== 'string') return Promise.resolve();
      try {
        const parsed: unknown = JSON.parse(value);
        return parsed !== null && typeof parsed === 'object'
          ? Promise.resolve()
          : Promise.reject(new Error(message));
      } catch {
        return Promise.reject(new Error(message));
      }
    },
  });

/** 表单回显：JSON 值 → 格式化文本（已是字符串时原样返回，空值为空串），可直接作为 ProForm 字段的 convertValue */
export function stringifyJson(value: unknown): string {
  if (value === undefined || value === null) return '';
  if (typeof value === 'string') return value;
  return JSON.stringify(value, null, 2);
}

/** 表单提交：JSON 文本 → 值；空文本为 null，非法文本原样返回（交给后端 422） */
export function parseJson(text: unknown): unknown {
  if (typeof text !== 'string') return text ?? null;
  if (text.trim() === '') return null;
  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

/**
 * 表单提交：日期时间 → 带时区偏移的 ISO 8601（契约第 2 节），如 `2026-11-16T18:30:00+08:00`，
 * 后端据此换算到服务器时区，浏览器与服务器不在同一时区时也不会偏差。用在 `ProFormDateTimePicker` 的 `transform` 中：
 * ProForm 先把 dayjs 值格式化成本地时间字符串（`YYYY-MM-DD HH:mm:ss`）再交给 transform，这里按浏览器时区补上偏移。
 * 空值为 null，无法解析的值原样提交（交给后端 422）。
 *
 * @example
 * transform={(value: unknown) => ({ published_at: toOffsetDateTime(value) })}
 */
export function toOffsetDateTime(value: unknown): unknown {
  if (value === undefined || value === null || value === '') return null;
  if (typeof value !== 'string' && !dayjs.isDayjs(value)) return value;
  const date = dayjs(value);
  return date.isValid() ? date.format() : value;
}
