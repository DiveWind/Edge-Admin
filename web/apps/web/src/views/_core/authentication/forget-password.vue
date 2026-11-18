<script lang="ts" setup>
import type { VbenFormSchema } from '@vben/common-ui';
import type { Recordable } from '@vben/types';

import { computed, ref } from 'vue';

import { AuthenticationForgetPassword, z } from '@vben/common-ui';
import { $t } from '@vben/locales';

import { message } from 'antdv-next';

import { setFormErrors } from '#/adapter/form';
import { forgotPasswordApi } from '#/api';
import { getValidationErrors } from '#/api/request';

defineOptions({ name: 'ForgetPassword' });

const loading = ref(false);
const formRef = ref<InstanceType<typeof AuthenticationForgetPassword>>();

const formSchema = computed((): VbenFormSchema[] => {
  return [
    {
      component: 'VbenInput',
      componentProps: {
        autocomplete: 'email',
        placeholder: 'example@example.com',
      },
      fieldName: 'email',
      label: $t('authentication.email'),
      rules: z
        .string()
        .min(1, { message: $t('authentication.emailTip') })
        .email($t('authentication.emailValidErrorTip')),
    },
  ];
});

/**
 * POST /forgot-password：发送重置密码邮件，邮件中的链接打开重置密码页。
 * 后端无论邮箱是否注册都返回成功（不暴露哪些邮箱已注册，§6.2），这里统一显示中性提示；
 * 邮箱格式错误（422）回填到表单，限流（429）等由请求层提示。
 */
async function handleSubmit(values: Recordable<any>) {
  if (loading.value) {
    return;
  }
  loading.value = true;
  try {
    await forgotPasswordApi({ email: String(values.email).trim() });
    message.success($t('auth.forgetPassword.sent'));
  } catch (error) {
    setFormErrors(formRef.value?.getFormApi(), getValidationErrors(error));
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <AuthenticationForgetPassword
    ref="formRef"
    :form-schema="formSchema"
    :loading="loading"
    @submit="handleSubmit"
  >
    <template #title>{{ $t('auth.forgetPassword.title') }}</template>
  </AuthenticationForgetPassword>
</template>
