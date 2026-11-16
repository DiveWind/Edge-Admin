import type { ProSchemaValueEnumType } from '@ant-design/pro-components';
import { useMemo } from 'react';
import { useT } from '@/utils/i18n';
import type { TaskLog } from './data';

/** 执行状态 valueEnum，任务列表的"最近执行"与执行日志共用 */
export default function useTaskLogStatusEnum(): Record<
  TaskLog['status'],
  ProSchemaValueEnumType
> {
  const t = useT();
  return useMemo(
    () => ({
      running: {
        text: t('pages.monitor.tasks.logStatus.running'),
        status: 'Processing',
      },
      success: {
        text: t('pages.monitor.tasks.logStatus.success'),
        status: 'Success',
      },
      failed: {
        text: t('pages.monitor.tasks.logStatus.failed'),
        status: 'Error',
      },
    }),
    [t],
  );
}
