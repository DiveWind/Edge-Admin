<script setup lang="ts">
import { computed, ref } from 'vue';

import { Profile } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import { $t } from '#/locales';

import ProfileBase from './base-setting.vue';
import ProfilePasswordSetting from './password-setting.vue';
import ProfileSecuritySetting from './security-setting.vue';

const userStore = useUserStore();

const tabsValue = ref<string>('basic');

const tabs = computed(() => [
  {
    label: $t('auth.profile.basic'),
    value: 'basic',
  },
  {
    label: $t('auth.profile.password'),
    value: 'password',
  },
  {
    label: $t('auth.profile.security'),
    value: 'security',
  },
]);
</script>
<template>
  <Profile
    v-model:model-value="tabsValue"
    :title="$t('page.auth.profile')"
    :user-info="userStore.userInfo"
    :tabs="tabs"
  >
    <template #content>
      <ProfileBase v-if="tabsValue === 'basic'" />
      <ProfilePasswordSetting v-if="tabsValue === 'password'" />
      <ProfileSecuritySetting v-if="tabsValue === 'security'" />
    </template>
  </Profile>
</template>
