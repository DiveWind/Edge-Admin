<script setup lang="ts">
import type { Recordable } from '@vben/types';

import type { VbenFormSchema } from '#/adapter/form';
import type { UserApi } from '#/api';

import { computed, onMounted, ref } from 'vue';

import { ProfileBaseSetting } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import { message } from 'antdv-next';

import { setFormErrors, z } from '#/adapter/form';
import { updateProfileApi } from '#/api';
import { getValidationErrors } from '#/api/request';
import { $t } from '#/locales';
import { useAuthStore } from '#/store';

const userStore = useUserStore();
const authStore = useAuthStore();

const profileBaseSettingRef = ref<InstanceType<typeof ProfileBaseSetting>>();
const submitting = ref(false);

// 字段名与 PUT /user/profile-information 一致，422 的错误可直接回填
const formSchema = computed((): VbenFormSchema[] => {
  return [
    {
      component: 'Input',
      componentProps: {
        autocomplete: 'off',
        disabled: true,
      },
      fieldName: 'username',
      label: $t('auth.profile.username'),
    },
    {
      component: 'Input',
      componentProps: {
        maxlength: 255,
      },
      fieldName: 'name',
      label: $t('auth.profile.name'),
      rules: 'required',
    },
    {
      component: 'Input',
      componentProps: {
        autocomplete: 'email',
        maxlength: 255,
        placeholder: $t('auth.profile.emailPlaceholder'),
      },
      fieldName: 'email',
      label: $t('auth.profile.email'),
      rules: z
        .string()
        .email($t('authentication.emailValidErrorTip'))
        .optional()
        .or(z.literal('')),
    },
    {
      component: 'Input',
      componentProps: {
        maxlength: 2048,
        placeholder: 'https://',
      },
      fieldName: 'avatar',
      label: $t('auth.profile.avatar'),
      rules: z
        .string()
        .url($t('auth.profile.avatarInvalid'))
        .optional()
        .or(z.literal('')),
    },
  ];
});

function fillForm() {
  const userInfo = userStore.userInfo;
  profileBaseSettingRef.value?.getFormApi().setValues({
    avatar: userInfo?.avatar ?? '',
    email: '',
    name: userInfo?.realName ?? '',
    username: userInfo?.username ?? '',
  });
}

/**
 * PUT /user/profile-information：
 * - 邮箱留空表示不修改（当前邮箱不在 GET /user/info 中，无法回显）；
 * - 头像地址留空表示清除（使用默认头像）。
 * 成功后重新获取用户信息，顶栏与个人中心随之更新。
 */
async function handleSubmit(values: Recordable<any>) {
  if (submitting.value) {
    return;
  }
  const email = String(values.email ?? '').trim();
  const avatar = String(values.avatar ?? '').trim();
  const params: UserApi.UpdateProfileParams = {
    avatar: avatar === '' ? null : avatar,
    name: String(values.name ?? '').trim(),
    ...(email === '' ? {} : { email }),
  };

  submitting.value = true;
  try {
    await updateProfileApi(params);
    await authStore.fetchUserInfo();
    fillForm();
    message.success($t('auth.profile.updated'));
  } catch (error) {
    setFormErrors(
      profileBaseSettingRef.value?.getFormApi(),
      getValidationErrors(error),
    );
  } finally {
    submitting.value = false;
  }
}

onMounted(fillForm);
</script>
<template>
  <ProfileBaseSetting
    ref="profileBaseSettingRef"
    :form-schema="formSchema"
    @submit="handleSubmit"
  />
</template>
