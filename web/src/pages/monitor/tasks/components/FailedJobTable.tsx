import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { App, Popconfirm, Typography } from 'antd';
import type React from 'react';
import { useRef } from 'react';
import { DeleteAction } from '@/components';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import type { FailedJob } from '../data';
import { deleteFailedJob, queryFailedJobs, retryFailedJob } from '../service';

const FailedJobTable: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);

  const reload = () => actionRef.current?.reload();

  const handleRetry = async (job: FailedJob) => {
    await retryFailedJob(job.uuid);
    message.success(t('pages.monitor.failedJobs.retried'));
    reload();
  };

  const columns: ProColumns<FailedJob>[] = [
    { title: t('pages.monitor.failedJobs.name'), dataIndex: 'name' },
    { title: t('pages.monitor.failedJobs.queue'), dataIndex: 'queue' },
    {
      title: t('pages.monitor.failedJobs.connection'),
      dataIndex: 'connection',
    },
    {
      title: t('pages.monitor.failedJobs.exception'),
      dataIndex: 'exception',
      ellipsis: true,
    },
    {
      title: t('pages.monitor.failedJobs.failedAt'),
      dataIndex: 'failed_at',
      valueType: 'dateTime',
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 140,
      render: (_, record) => [
        access.can('monitor.failed-job.retry') && (
          <Popconfirm
            key="retry"
            title={t('pages.monitor.failedJobs.retryConfirm')}
            onConfirm={() => handleRetry(record).catch(() => undefined)}
          >
            <Typography.Link>
              {t('pages.monitor.failedJobs.retry')}
            </Typography.Link>
          </Popconfirm>
        ),
        access.can('monitor.failed-job.delete') && (
          <DeleteAction
            key="delete"
            onConfirm={() => deleteFailedJob(record.uuid)}
            onSuccess={reload}
          />
        ),
      ],
    },
  ];

  return (
    <ProTable<FailedJob>
      rowKey="uuid"
      actionRef={actionRef}
      columns={columns}
      search={false}
      request={tableRequest(queryFailedJobs)}
      expandable={{
        expandedRowRender: (record) => (
          <Typography.Paragraph
            style={{ whiteSpace: 'pre-wrap', marginBottom: 0 }}
            copyable
          >
            {record.exception}
          </Typography.Paragraph>
        ),
      }}
    />
  );
};

export default FailedJobTable;
