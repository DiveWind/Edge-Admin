import type { ProSchemaValueEnumType } from '@ant-design/pro-components';
import { useMemo } from 'react';
import { useT } from '@/utils/i18n';

/** 多个页面共用的枚举 → ProTable / ProForm `valueEnum`（文案随语言切换） */

export function useStatusEnum(): Record<API.Status, ProSchemaValueEnumType> {
  const t = useT();
  return useMemo(
    () => ({
      active: { text: t('common.status.active'), status: 'Success' },
      disabled: { text: t('common.status.disabled'), status: 'Default' },
    }),
    [t],
  );
}

export function usePublishStatusEnum(): Record<
  API.PublishStatus,
  ProSchemaValueEnumType
> {
  const t = useT();
  return useMemo(
    () => ({
      draft: { text: t('common.publishStatus.draft'), status: 'Default' },
      published: {
        text: t('common.publishStatus.published'),
        status: 'Success',
      },
    }),
    [t],
  );
}

export function useDataScopeEnum(): Record<
  API.DataScope,
  ProSchemaValueEnumType
> {
  const t = useT();
  return useMemo(
    () => ({
      all: { text: t('common.dataScope.all') },
      custom: { text: t('common.dataScope.custom') },
      department_and_children: {
        text: t('common.dataScope.department_and_children'),
      },
      department: { text: t('common.dataScope.department') },
      self: { text: t('common.dataScope.self') },
    }),
    [t],
  );
}
