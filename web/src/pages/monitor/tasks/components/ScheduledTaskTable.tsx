import { PlayCircleOutlined } from '@ant-design/icons';
import type { ProColumns } from '@ant-design/pro-components';
import { ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { App, Popconfirm, Space, Typography } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';
import type { ScheduledTask } from '../data';
import { getScheduledTasks, runScheduledTask } from '../service';
import useTaskLogStatusEnum from '../useTaskLogStatusEnum';

interface ScheduledTaskTableProps {
  /** 查看某个任务的执行日志 */
  onViewLogs: (task: string) => void;
}

const ScheduledTaskTable: React.FC<ScheduledTaskTableProps> = ({
  onViewLogs,
}) => {
  const t = useT();
  const access = useAccess();
  const { message } = App.useApp();
  const logStatusEnum = useTaskLogStatusEnum();

  const handleRun = async (task: ScheduledTask) => {
    await runScheduledTask(task.key);
    message.success(t('pages.monitor.tasks.queued'));
  };

  const columns: ProColumns<ScheduledTask>[] = [
    {
      title: t('pages.monitor.tasks.command'),
      dataIndex: 'command',
      copyable: true,
      render: (_, record) => (
        <Space orientation="vertical" size={0}>
          <Typography.Text code>{record.command}</Typography.Text>
          {record.description && (
            <Typography.Text type="secondary">
              {record.description}
            </Typography.Text>
          )}
        </Space>
      ),
    },
    {
      title: t('pages.monitor.tasks.expression'),
      dataIndex: 'expression',
      render: (_, record) => (
        <Typography.Text code>{record.expression}</Typography.Text>
      ),
    },
    {
      title: t('pages.monitor.tasks.nextRunAt'),
      dataIndex: 'next_run_at',
      valueType: 'dateTime',
    },
    {
      title: t('pages.monitor.tasks.lastStatus'),
      dataIndex: ['last_log', 'status'],
      valueEnum: logStatusEnum,
    },
    {
      title: t('pages.monitor.tasks.lastRunAt'),
      dataIndex: ['last_log', 'started_at'],
      valueType: 'dateTime',
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 180,
      render: (_, record) => [
        access.can('monitor.scheduled-task.run') && (
          <Popconfirm
            key="run"
            title={t('pages.monitor.tasks.runConfirm')}
            onConfirm={() => handleRun(record).catch(() => undefined)}
          >
            <Typography.Link>
              <PlayCircleOutlined /> {t('pages.monitor.tasks.run')}
            </Typography.Link>
          </Popconfirm>
        ),
        <Typography.Link key="logs" onClick={() => onViewLogs(record.key)}>
          {t('pages.monitor.tasks.viewLogs')}
        </Typography.Link>,
      ],
    },
  ];

  return (
    <ProTable<ScheduledTask>
      rowKey="key"
      columns={columns}
      search={false}
      pagination={false}
      request={async () => {
        const { data } = await getScheduledTasks();
        return { data, success: true };
      }}
    />
  );
};

export default ScheduledTaskTable;
