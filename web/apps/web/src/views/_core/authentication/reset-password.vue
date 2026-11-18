<script lang="ts" setup>
import type { VbenFormSchema } from '@vben/common-ui';
import type { Recordable } from '@vben/types';

import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { AuthenticationForgetPassword, z } from '@vben/common-ui';
import { LOGIN_PATH } from '@vben/constants';
import { $t } from '@vben/locales';

import { message } from 'antdv-next';

import { setFormErrors } from '#/adapter/form';
import { resetPasswordApi } from '#/api';
import { getValidationErrors } from '#/api/request';

defineOptions({ name: 'ResetPassword' });

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const formRef = ref<InstanceType<typeof AuthenticationForgetPassword>>();

/** 重置链接：/#/auth/reset-password?token=…&email=… */
const token = computed(() => String(route.query.token ?? ''));
const email = computed(() => String(route.query.email ?? ''));
const linkValid = computed(() => token.value !== '' && email.value !== '');

const formSchema = computed((): VbenFormSchema[] => {
  return [
    {
      component: 'VbenInput',
      componentProps: {
        autocomplete: 'username',
        disabled: true,
      },
      defaultValue: email.value,
      fieldName: 'email',
      label: $t('authentication.email'),
    },
    {
      component: 'VbenInputPassword',
      componentProps: {
        autocomplete: 'new-password',
        passwordStrength: true,
        placeholder: $t('auth.resetPassword.password'),
      },
      fieldName: 'password',
      label: $t('auth.resetPassword.password'),
      rules: z
        .string()
        .min(1, { message: $t('auth.resetPassword.passwordRequired') }),
    },
    {
      component: 'VbenInputPassword',
      componentProps: {
        autocomplete: 'new-password',
        placeholder: $t('authentication.confirmPassword'),
      },
      dependencies: {
        rules(values) {
          const { password } = values;
          return z
            .string({
              required_error: $t('auth.resetPassword.confirmRequired'),
            })
            .min(1, { message: $t('auth.resetPassword.confirmRequired') })
            .refine((value) => value === password, {
              message: $t('authentication.confirmPasswordTip'),
            });
        },
        triggerFields: ['password'],
      },
      fieldName: 'password_confirmation',
      label: $t('authentication.confirmPassword'),
    },
  ];
});

onMounted(() => {
  if (!linkValid.value) {
    message.error($t('auth.resetPassword.invalidLink'));
  }
});

/**
 * POST /reset-password：成功后回到登录页（该账号已登录的会话全部失效）
 */
async function handleSubmit(values: Recordable<any>) {
  if (loading.value) {
    return;
  }
  if (!linkValid.value) {
    message.error($t('auth.resetPassword.invalidLink'));
    return;
  }
  loading.value = true;
  try {
    const result = await resetPasswordApi({
      email: email.value,
      password: String(values.password),
      password_confirmation: String(values.password_confirmation),
      token: token.value,
    });
    message.success(result?.message || $t('auth.resetPassword.success'));
    await router.replace(LOGIN_PATH);
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
    :sub-title="
      linkValid
        ? $t('auth.resetPassword.subtitle')
        : $t('auth.resetPassword.invalidLink')
    "
    :submit-button-text="$t('auth.resetPassword.submit')"
    @submit="handleSubmit"
  >
    <template #title>{{ $t('auth.resetPassword.title') }}</template>
  </AuthenticationForgetPassword>
</template>
