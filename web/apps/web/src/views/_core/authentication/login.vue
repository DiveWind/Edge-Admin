<script lang="ts" setup>
import type { VbenFormSchema } from '@vben/common-ui';
import type { Recordable } from '@vben/types';

import { computed, ref } from 'vue';

import { AuthenticationLogin, z } from '@vben/common-ui';
import { $t } from '@vben/locales';

import { setFormErrors } from '#/adapter/form';
import { getValidationErrors } from '#/api/request';
import { useAuthStore } from '#/store';

defineOptions({ name: 'Login' });

const authStore = useAuthStore();

const loginRef = ref<InstanceType<typeof AuthenticationLogin>>();

// 只提交 username、password、remember（后端拒绝其他字段）
const formSchema = computed((): VbenFormSchema[] => {
  return [
    {
      component: 'VbenInput',
      componentProps: {
        autocomplete: 'username',
        placeholder: $t('authentication.usernameTip'),
      },
      fieldName: 'username',
      label: $t('authentication.username'),
      rules: z.string().min(1, { message: $t('authentication.usernameTip') }),
    },
    {
      component: 'VbenInputPassword',
      componentProps: {
        autocomplete: 'current-password',
        placeholder: $t('authentication.password'),
      },
      fieldName: 'password',
      label: $t('authentication.password'),
      rules: z.string().min(1, { message: $t('authentication.passwordTip') }),
    },
    {
      component: 'VbenCheckbox',
      defaultValue: false,
      fieldName: 'remember',
      renderComponentContent: () => ({
        default: () => $t('auth.login.remember'),
      }),
    },
  ];
});

async function handleSubmit(values: Recordable<any>) {
  try {
    await authStore.authLogin(values);
  } catch (error) {
    // 422：用户名或密码错误、账号已停用等，显示在对应字段下
    setFormErrors(loginRef.value?.getFormApi(), getValidationErrors(error));
  }
}
</script>

<template>
  <AuthenticationLogin
    ref="loginRef"
    :form-schema="formSchema"
    :loading="authStore.loginLoading"
    :show-code-login="false"
    :show-qrcode-login="false"
    :show-register="false"
    :show-remember-me="false"
    :show-third-party-login="false"
    @submit="handleSubmit"
  />
</template>
