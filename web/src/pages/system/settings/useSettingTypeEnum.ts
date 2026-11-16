import type { ProSchemaValueEnumType } from '@ant-design/pro-components';
import { useMemo } from 'react';
import { useT } from '@/utils/i18n';
import type { SettingType } from './data';

export function useSettingTypeEnum(): Record<
  SettingType,
  ProSchemaValueEnumType
> {
  const t = useT();
  return useMemo(
    () => ({
      string: { text: t('pages.system.settings.type.string') },
      number: { text: t('pages.system.settings.type.number') },
      boolean: { text: t('pages.system.settings.type.boolean') },
      json: { text: t('pages.system.settings.type.json') },
    }),
    [t],
  );
}
