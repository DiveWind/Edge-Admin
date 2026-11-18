<script setup lang="ts">
import type { TwoFactorApi } from '#/api';

import { computed, onMounted, ref } from 'vue';

import { confirm, useVbenModal } from '@vben/common-ui';

import { useClipboard } from '@vueuse/core';
import { Alert, Button, message, Space, Spin, Tag } from 'antdv-next';

import { setFormErrors, useVbenForm, z } from '#/adapter/form';
import {
  confirmTwoFactorApi,
  disableTwoFactorApi,
  enableTwoFactorApi,
  getConfirmedPasswordStatusApi,
  getRecoveryCodesApi,
  getTwoFactorQrCodeApi,
  getTwoFactorSecretKeyApi,
  regenerateRecoveryCodesApi,
} from '#/api';
import { getValidationErrors } from '#/api/request';
import { $t } from '#/locales';

/**
 * 两步验证设置（Fortify）。这些接口都要求最近确认过密码：未确认时返回 423，请求层弹出确认密码框后自动重试。
 *
 * 状态：接口没有直接返回“是否已开启”，确认密码后按二维码接口判断（有二维码 = 已开启）。
 * 开启后必须用动态码确认才生效；在确认前关闭设置弹窗会撤销本次开启，避免留下未确认的密钥。
 */

/** 页面状态：loading 加载中；locked 需要先确认密码；ready 已知开启状态 */
const status = ref<'loading' | 'locked' | 'ready'>('loading');
const enabled = ref(false);
const busy = ref(false);

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
      await loadStatus();
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
      enabled.value = true;
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

/** 按二维码接口判断是否已开启（未确认密码时会弹出确认框） */
async function loadStatus() {
  const qrCode = await getTwoFactorQrCodeApi();
  enabled.value = isQrCode(qrCode);
  status.value = 'ready';
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

function handleUnlock() {
  return run(loadStatus);
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
        enabled.value = false;
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

onMounted(async () => {
  try {
    const { confirmed } = await getConfirmedPasswordStatusApi();
    if (confirmed) {
      await loadStatus();
    } else {
      status.value = 'locked';
    }
  } catch {
    status.value = 'locked';
  }
});
</script>

<template>
  <div class="space-y-4">
    <Spin :spinning="status === 'loading'">
      <div
        class="flex flex-row items-center justify-between gap-4 rounded-lg border p-4"
      >
        <div class="space-y-1">
          <div class="flex items-center gap-2 text-base font-medium">
            {{ $t('auth.security.title') }}
            <template v-if="status === 'ready'">
              <Tag v-if="enabled" color="success">
                {{ $t('auth.security.statusEnabled') }}
              </Tag>
              <Tag v-else>{{ $t('auth.security.statusDisabled') }}</Tag>
            </template>
          </div>
          <div class="text-sm text-muted-foreground">
            {{
              status === 'locked'
                ? $t('auth.security.lockedDesc')
                : $t('auth.security.description')
            }}
          </div>
        </div>
        <Space>
          <Button
            v-if="status === 'locked'"
            :loading="busy"
            type="primary"
            @click="handleUnlock"
          >
            {{ $t('auth.security.unlock') }}
          </Button>
          <template v-else-if="status === 'ready'">
            <Button
              v-if="!enabled"
              :loading="busy"
              type="primary"
              @click="handleEnable"
            >
              {{ $t('auth.security.enable') }}
            </Button>
            <template v-else>
              <Button :loading="busy" @click="showRecoveryCodes">
                {{ $t('auth.security.recoveryCodes') }}
              </Button>
              <Button danger :disabled="busy" @click="handleDisable">
                {{ $t('auth.security.disable') }}
              </Button>
            </template>
          </template>
        </Space>
      </div>
    </Spin>

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
