import type { FormInstance, FormRule } from 'antd';
import { getFieldErrors, isRequestError } from './error';

/**
 * ModalForm / DrawerForm 的 onFinish 包装：成功返回 true（关闭弹窗），
 * 失败时把 422 的 `errors` 回填到表单字段并返回 false。
 * 错误提示（message）由全局 errorHandler 负责，这里不重复提示。
 *
 * @example
 * onFinish={(values) => submitForm(form, () => createUser(values))}
 */
export async function submitForm(
  form: FormInstance,
  action: () => Promise<unknown>,
): Promise<boolean> {
  try {
    await action();
    return true;
  } catch (error) {
    // 非请求错误（代码缺陷）继续抛出，不吞掉
    if (!isRequestError(error)) throw error;
    const fieldErrors = getFieldErrors(error);
    if (fieldErrors.length) form.setFields(fieldErrors);
    return false;
  }
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
