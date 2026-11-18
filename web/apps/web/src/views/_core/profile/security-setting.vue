<script setup lang="ts">
import type { Recordable } from '@vben/types';

import type { TwoFactorApi, UserApi } from '#/api';

import { computed, onMounted, ref } from 'vue';

import { confirm, ProfileSecuritySetting, useVbenModal } from '@vben/common-ui';
import { useUserStore } from '@vben/stores';

import { useClipboard } from '@vueuse/core';
import { Alert, Button, message, Space, Spin } from 'antdv-next';

import { setFormErrors, useVbenForm, z } from '#/adapter/form';
import {
  confirmTwoFactorApi,
  disableTwoFactorApi,
  enableTwoFactorApi,
  getRecoveryCodesApi,
  getTwoFactorQrCodeApi,
  getTwoFactorSecretKeyApi,
  regenerateRecoveryCodesApi,
} from '#/api';
import { getValidationErrors } from '#/api/request';
import { $t } from '#/locales';
import { useAuthStore } from '#/store';

/**
 * 两步验证设置（Fortify）。开启、关闭、二维码、恢复码等接口都要求最近确认过密码：未确认时返回 423，
 * 请求层弹出确认密码框后自动重试。
 *
 * 界面：Vben 的 ProfileSecuritySetting（开关列表），开关即两步验证状态，打开为开启流程、关闭为关闭流程；
 * 开关由状态决定，流程取消或失败时保持原样。已开启时下方提供查看恢复码。
 * 状态：GET /user/info 的 twoFactorEnabled（已确认开启）。进入页面时重新获取一次用户信息，
 * 开启、关闭后同样重新获取，顶栏等处的用户信息随之更新。
 * 开启后必须用动态码确认才生效；在确认前关闭设置弹窗会撤销本次开启，避免留下未确认的密钥。
 */

const userStore = useUserStore();
const authStore = useAuthStore();

/** 正在获取最新状态 */
const loading = ref(false);
const busy = ref(false);

const enabled = computed(
  () =>
    (userStore.userInfo as null | UserApi.UserInfo)?.twoFactorEnabled === true,
);

const securitySchema = computed(() => [
  {
    description: $t('auth.security.description'),
    fieldName: 'twoFactor',
    label: $t('auth.security.title'),
    value: enabled.value,
  },
]);

const setup = ref<{ qrCode: string; secretKey: string }>({
  qrCode: '',
  secretKey: '',
});
/** 本次开启是否已用动态码确认 */
const setupConfirmed = ref(false);

const recoveryCodes = ref<string[]>([]);

const { copy } = useClipboard({ legacy: true });

const qrCodeSrc = computed(() =>
  setup.value.qrCode
    ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(setup.value.qrCode)}`
    : '',
);

const [CodeForm, codeFormApi] = useVbenForm({
  commonConfig: {
    hideLabel: true,
  },
  schema: [
    {
      component: 'Input',
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
  showDefaultActions: false,
});

const [SetupModal, setupModalApi] = useVbenModal({
  confirmText: $t('auth.security.confirmEnable'),
  fullscreenButton: false,
  async onClosed() {
    // 未确认就关闭：撤销本次开启
    if (!setupConfirmed.value) {
      try {
        await disableTwoFactorApi();
      } catch {
        // 撤销失败时以服务端状态为准
      }
      await refreshStatus();
    }
  },
  async onConfirm() {
    const { valid } = await codeFormApi.validate();
    if (!valid) {
      return;
    }
    const { code } = await codeFormApi.getValues();
    setupModalApi.lock();
    try {
      await confirmTwoFactorApi(String(code).trim());
      setupConfirmed.value = true;
      await refreshStatus();
      await setupModalApi.close();
      message.success($t('auth.security.enabled'));
      await showRecoveryCodes();
    } catch (error) {
      setFormErrors(codeFormApi, getValidationErrors(error));
    } finally {
      setupModalApi.unlock();
    }
  },
});

const [RecoveryModal, recoveryModalApi] = useVbenModal({
  fullscreenButton: false,
  showCancelButton: false,
  onConfirm() {
    recoveryModalApi.close();
  },
});

function isQrCode(value: unknown): value is TwoFactorApi.QrCode {
  return typeof value === 'object' && value !== null && 'svg' in value;
}

/** 重新获取用户信息（twoFactorEnabled）；失败时保留当前状态（错误提示由请求层统一处理） */
async function refreshStatus() {
  try {
    await authStore.fetchUserInfo();
  } catch {
    // 401 等已由请求层处理
  }
}

/** 执行一个操作；用户取消确认密码时静默结束（错误提示由请求层统一处理） */
async function run(action: () => Promise<void>) {
  if (busy.value) {
    return;
  }
  busy.value = true;
  try {
    await action();
  } catch {
    // 已由请求层提示，或用户取消了确认密码
  } finally {
    busy.value = false;
  }
}

function handleEnable() {
  return run(async () => {
    await enableTwoFactorApi();
    const [qrCode, secret] = await Promise.all([
      getTwoFactorQrCodeApi(),
      getTwoFactorSecretKeyApi(),
    ]);
    setup.value = {
      qrCode: isQrCode(qrCode) ? qrCode.svg : '',
      secretKey: secret.secretKey,
    };
    setupConfirmed.value = false;
    // 弹窗关闭时销毁内容（destroyOnClose），每次打开都是空的动态码表单
    setupModalApi.open();
  });
}

function showRecoveryCodes() {
  return run(async () => {
    recoveryCodes.value = await getRecoveryCodesApi();
    recoveryModalApi.open();
  });
}

function handleRegenerate() {
  return run(async () => {
    await regenerateRecoveryCodesApi();
    recoveryCodes.value = await getRecoveryCodesApi();
    message.success($t('auth.security.recoveryCodesRegenerated'));
  });
}

async function handleCopy(text: string) {
  await copy(text);
  message.success($t('auth.security.copied'));
}

function handleDisable() {
  confirm({
    async beforeClose({ isConfirm }) {
      if (!isConfirm) {
        return true;
      }
      try {
        await disableTwoFactorApi();
        await refreshStatus();
        message.success($t('auth.security.disabled'));
        return true;
      } catch {
        return false;
      }
    },
    content: $t('auth.security.disableConfirm'),
    icon: 'warning',
    title: $t('auth.security.disable'),
  }).catch(() => {
    // 取消
  });
}

/** 开关：打开为开启流程，关闭为关闭流程（加载或操作进行中时忽略） */
function handleChange({ value }: Recordable<any>) {
  if (loading.value || busy.value) {
    return;
  }
  if (value) {
    void handleEnable();
  } else {
    handleDisable();
  }
}

onMounted(async () => {
  loading.value = true;
  await refreshStatus();
  loading.value = false;
});
</script>

<template>
  <div class="flex flex-col items-stretch gap-4">
    <Spin :spinning="loading || busy">
      <ProfileSecuritySetting
        :form-schema="securitySchema"
        @change="handleChange"
      />
    </Spin>
    <Space v-if="enabled" class="self-start">
      <Button @click="showRecoveryCodes">
        {{ $t('auth.security.recoveryCodes') }}
      </Button>
    </Space>

    <SetupModal class="w-[520px]" :title="$t('auth.security.setupTitle')">
      <div class="space-y-4">
        <p class="text-sm">{{ $t('auth.security.setupScan') }}</p>
        <div class="flex justify-center">
          <img
            v-if="qrCodeSrc"
            :alt="$t('auth.security.qrCode')"
            :src="qrCodeSrc"
            class="size-48 rounded bg-white p-2"
          />
        </div>
        <div class="text-sm">
          {{ $t('auth.security.secretKey') }}
          <code
            class="ml-1 rounded bg-muted px-2 py-1 font-mono tracking-wider"
            data-testid="two-factor-secret"
          >
            {{ setup.secretKey }}
          </code>
          <Button size="small" type="link" @click="handleCopy(setup.secretKey)">
            {{ $t('auth.security.copy') }}
          </Button>
        </div>
        <p class="text-sm">{{ $t('auth.security.setupCode') }}</p>
        <CodeForm />
      </div>
    </SetupModal>

    <RecoveryModal
      class="w-[520px]"
      :confirm-text="$t('auth.security.done')"
      :title="$t('auth.security.recoveryCodes')"
    >
      <div class="space-y-4">
        <Alert :title="$t('auth.security.recoveryCodesTip')" type="warning" />
        <div
          class="grid grid-cols-2 gap-2 font-mono text-sm"
          data-testid="recovery-codes"
        >
          <code
            v-for="item in recoveryCodes"
            :key="item"
            class="rounded bg-muted px-2 py-1 text-center"
          >
            {{ item }}
          </code>
        </div>
        <Space>
          <Button @click="handleCopy(recoveryCodes.join('\n'))">
            {{ $t('auth.security.copy') }}
          </Button>
          <Button :loading="busy" danger @click="handleRegenerate">
            {{ $t('auth.security.regenerate') }}
          </Button>
        </Space>
      </div>
    </RecoveryModal>
  </div>
</template>
