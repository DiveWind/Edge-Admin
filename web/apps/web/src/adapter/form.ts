import type {
  ExtendedFormApi,
  VbenFormProps as FormProps,
  VbenFormSchema as FormSchema,
} from '@vben/common-ui';

import type { ComponentPropsMap, ComponentType } from './component';

import { setupVbenForm, useVbenForm as useForm, z } from '@vben/common-ui';
import { $t } from '@vben/locales';

async function initSetupVbenForm() {
  setupVbenForm<ComponentType>({
    config: {
      // ant design vue组件库默认都是 v-model:value
      baseModelPropName: 'value',

      // 一些组件是 v-model:checked 或者 v-model:fileList
      modelPropNameMap: {
        Checkbox: 'checked',
        Radio: 'checked',
        Switch: 'checked',
        Upload: 'fileList',
      },
    },
    defineRules: {
      // 输入项目必填国际化适配
      required: (value, _params, ctx) => {
        if (value === undefined || value === null || value.length === 0) {
          return $t('ui.formRules.required', [ctx.label]);
        }
        return true;
      },
      // 选择项目必填国际化适配
      selectRequired: (value, _params, ctx) => {
        if (value === undefined || value === null) {
          return $t('ui.formRules.selectRequired', [ctx.label]);
        }
        return true;
      },
    },
  });
}
const useVbenForm = useForm<ComponentType, ComponentPropsMap>;

/**
 * 把接口 422 的字段错误（`{ 字段: [错误] }`）显示到表单对应字段下；表单中没有的字段忽略。
 * 表单字段名与接口字段名一致（如 current_password、password_confirmation）。
 * @returns 是否回填了至少一个字段
 */
function setFormErrors(
  formApi: ExtendedFormApi | undefined,
  errors: Record<string, string[]> | undefined,
): boolean {
  if (!formApi || !errors) {
    return false;
  }
  const fields = new Set(
    (formApi.getState()?.schema ?? []).map((item) => item.fieldName),
  );
  const matched = Object.fromEntries(
    Object.entries(errors).filter(
      ([field, messages]) => fields.has(field) && messages.length > 0,
    ),
  );
  formApi.form.setErrors(
    Object.fromEntries(
      Object.entries(matched).map(([field, messages]) => [field, messages[0]]),
    ),
  );
  return Object.keys(matched).length > 0;
}

export { initSetupVbenForm, setFormErrors, useVbenForm, z };

export type VbenFormSchema = FormSchema<ComponentType, ComponentPropsMap>;
export type VbenFormProps = FormProps<ComponentType, ComponentPropsMap>;
