import { getIntl, useIntl } from '@umijs/max';
import { useCallback } from 'react';
import type zhCN from '@/locales/zh-CN';

/**
 * 文案 key 以 zh-CN 为准，en-US 各模块用 `Record<keyof typeof zh, string>` 约束同步，
 * 因此 key 拼错或漏翻都会在 tsc 阶段报错。
 */
export type MessageId = keyof typeof zhCN;
export type MessageValues = Record<string, string | number>;

/** 组件内使用：`const t = useT(); t('common.create')` */
export function useT() {
  const intl = useIntl();
  return useCallback(
    (id: MessageId, values?: MessageValues) =>
      intl.formatMessage({ id }, values),
    [intl],
  );
}

/** 组件外使用（请求层等），取当前语言 */
export function t(id: MessageId, values?: MessageValues): string {
  return getIntl().formatMessage({ id }, values);
}
