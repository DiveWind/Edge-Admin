<script lang="ts" setup>
import type { VbenFormSchema } from '#/adapter/form';
import type { AuthApi } from '#/api';

import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import { VbenButton } from '@vben/common-ui';
import { LOGIN_PATH } from '@vben/constants';
import { $t } from '@vben/locales';

import { setFormErrors, useVbenForm, z } from '#/adapter/form';
import { getValidationErrors } from '#/api/request';
import { useAuthStore } from '#/store';

defineOptions({ name: 'TwoFactorChallenge' });

const authStore = useAuthStore();
const router = useRouter();

/** 动态码（身份验证器）或恢复码 */
const useRecoveryCode = ref(false);

const schema = computed((): VbenFormSchema[] =>
  useRecoveryCode.value
    ? [
        {
          component: 'VbenInput',
          componentProps: {
            autocomplete: 'off',
            placeholder: $t('auth.twoFactor.recoveryCodePlaceholder'),
          },
          fieldName: 'recovery_code',
          label: $t('auth.twoFactor.recoveryCode'),
          rules: z
            .string()
            .trim()
            .min(1, { message: $t('auth.twoFactor.recoveryCodeRequired') }),
        },
      ]
    : [
        {
          component: 'VbenInput',
          componentProps: {
            autocomplete: 'one-time-code',
            inputmode: 'numeric',
            maxlength: 6,
            placeholder: $t('auth.twoFactor.codePlaceholder'),
          },
          fieldName: 'code',
          label: $t('auth.twoFactor.code'),
          rules: z
            .string()
            .trim()
            .regex(/^\d{6}$/, { message: $t('auth.twoFactor.codeInvalid') }),
        },
      ],
);

const [Form, formApi] = useVbenForm(
  reactive({
    commonConfig: {
      hideLabel: true,
      hideRequiredMark: true,
    },
    schema,
    showDefaultActions: false,
  }),
);

function toggleMode() {
  useRecoveryCode.value = !useRecoveryCode.value;
}

async function handleSubmit() {
  const { valid } = await formApi.validate();
  if (!valid) {
    return;
  }
  const values = await formApi.getValues();
  const params: AuthApi.TwoFactorChallengeParams = useRecoveryCode.value
    ? { recovery_code: String(values.recovery_code).trim() }
    : { code: String(values.code).trim() };
  try {
    await authStore.authTwoFactor(params);
  } catch (error) {
    setFormErrors(formApi, getValidationErrors(error));
  }
}
</script>

<template>
  <div @keydown.enter.prevent="handleSubmit">
    <div class="mb-7 sm:mx-auto sm:w-full sm:max-w-md">
      <h2
        class="mb-3 text-3xl/9 font-bold tracking-tight text-foreground lg:text-4xl"
      >
        {{ $t('auth.twoFactor.title') }}
      </h2>
      <p class="lg:text-md text-sm text-muted-foreground">
        {{
          useRecoveryCode
            ? $t('auth.twoFactor.recoveryCodeSubtitle')
            : $t('auth.twoFactor.codeSubtitle')
        }}
      </p>
    </div>

    <Form />

    <VbenButton
      :class="{ 'cursor-wait': authStore.loginLoading }"
      :loading="authStore.loginLoading"
      aria-label="submit"
      class="w-full"
      @click="handleSubmit"
    >
      {{ $t('auth.twoFactor.submit') }}
    </VbenButton>

    <div class="mt-4 flex items-center justify-between text-sm">
      <span class="vben-link font-normal" @click="toggleMode">
        {{
          useRecoveryCode
            ? $t('auth.twoFactor.useCode')
            : $t('auth.twoFactor.useRecoveryCode')
        }}
      </span>
      <span class="vben-link font-normal" @click="router.push(LOGIN_PATH)">
        {{ $t('auth.twoFactor.backToLogin') }}
      </span>
    </div>
  </div>
</template>
