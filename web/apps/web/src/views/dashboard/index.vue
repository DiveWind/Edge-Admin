<script lang="ts" setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';

import { Page, VbenAvatar } from '@vben/common-ui';
import { preferences } from '@vben/preferences';
import { useUserStore } from '@vben/stores';

import { Button, Card } from 'antdv-next';

import { $t } from '#/locales';

defineOptions({ name: 'Dashboard' });

const router = useRouter();
const userStore = useUserStore();

const avatar = computed(
  () => userStore.userInfo?.avatar || preferences.app.defaultAvatar,
);
</script>

<template>
  <Page>
    <Card>
      <div class="flex items-center gap-6">
        <VbenAvatar :src="avatar" class="size-16" />
        <div class="flex flex-col gap-1">
          <h1 class="text-xl font-semibold">
            {{
              $t('page.dashboard.welcome', [
                userStore.userInfo?.realName ?? userStore.userInfo?.username,
              ])
            }}
          </h1>
          <span class="text-foreground/70">
            {{ $t('page.dashboard.description') }}
          </span>
        </div>
        <div class="flex flex-1 justify-end">
          <Button @click="router.push({ name: 'Profile' })">
            {{ $t('page.auth.profile') }}
          </Button>
        </div>
      </div>
    </Card>
  </Page>
</template>
