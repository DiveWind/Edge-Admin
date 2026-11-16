import type { ProSchemaValueEnumType } from '@ant-design/pro-components';
import { useMemo } from 'react';
import { useT } from '@/utils/i18n';
import type { AnnouncementType } from './data';

export default function useAnnouncementTypeEnum(): Record<
  AnnouncementType,
  ProSchemaValueEnumType
> {
  const t = useT();
  return useMemo(
    () => ({
      notice: { text: t('pages.message.announcements.type.notice') },
      announcement: {
        text: t('pages.message.announcements.type.announcement'),
      },
    }),
    [t],
  );
}
