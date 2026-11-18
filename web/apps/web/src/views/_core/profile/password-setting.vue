<script setup lang="ts">
import type { Recordable } from '@vben/types';

import type { VbenFormSchema } from '#/adapter/form';

import { computed, ref } from 'vue';

import { ProfilePasswordSetting } from '@vben/common-ui';

import { message } from 'antdv-next';

import { setFormErrors, z } from '#/adapter/form';
import { updatePasswordApi } from '#/api';
import { getValidationErrors } from '#/api/request';
import { $t } from '#/locales';

const passwordSettingRef = ref<InstanceType<typeof ProfilePasswordSetting>>();
const submitting = ref(false);

// 字段名与 PUT /user/password 一致，422 的错误可直接回填
const formSchema = computed((): VbenFormSchema[] => {
  return [
    {
      fieldName: 'current_password',
      label: $t('auth.password.current'),
      component: 'VbenInputPassword',
      componentProps: {
        autocomplete: 'current-password',
        placeholder: $t('auth.password.currentPlaceholder'),
      },
      rules: z
        .string()
        .min(1, { message: $t('auth.password.currentPlaceholder') }),
    },
    {
      fieldName: 'password',
      label: $t('auth.password.new'),
      component: 'VbenInputPassword',
      componentProps: {
        autocomplete: 'new-password',
        passwordStrength: true,
        placeholder: $t('auth.password.newPlaceholder'),
      },
      rules: z.string().min(1, { message: $t('auth.password.newPlaceholder') }),
    },
    {
      fieldName: 'password_confirmation',
      label: $t('auth.password.confirm'),
      component: 'VbenInputPassword',
      componentProps: {
        autocomplete: 'new-password',
        placeholder: $t('auth.password.confirmPlaceholder'),
      },
      dependencies: {
        rules(values) {
          const { password } = values;
          return z
            .string({ required_error: $t('auth.password.confirmPlaceholder') })
            .min(1, { message: $t('auth.password.confirmPlaceholder') })
            .refine((value) => value === password, {
              message: $t('authentication.confirmPasswordTip'),
            });
        },
        triggerFields: ['password'],
      },
    },
  ];
});

/**
 * PUT /user/password：当前会话保持登录，其他设备上的会话失效
 */
async function handleSubmit(values: Recordable<any>) {
  if (submitting.value) {
    return;
  }
  submitting.value = true;
  const formApi = passwordSettingRef.value?.getFormApi();
  try {
    await updatePasswordApi({
      current_password: String(values.current_password ?? ''),
      password: String(values.password ?? ''),
      password_confirmation: String(values.password_confirmation ?? ''),
    });
    await formApi?.resetForm();
    message.success($t('auth.password.updated'));
  } catch (error) {
    setFormErrors(formApi, getValidationErrors(error));
  } finally {
    submitting.value = false;
  }
}
</script>
<template>
  <ProfilePasswordSetting
    ref="passwordSettingRef"
    class="w-1/3"
    :form-schema="formSchema"
    @submit="handleSubmit"
  />
</template>
