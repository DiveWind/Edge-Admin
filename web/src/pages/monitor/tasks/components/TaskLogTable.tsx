import type { ProColumns } from '@ant-design/pro-components';
import { ProTable } from '@ant-design/pro-components';
import { Typography } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import type { TaskLog } from '../data';
import { queryTaskLogs } from '../service';
import useTaskLogStatusEnum from '../useTaskLogStatusEnum';

interface TaskLogTableProps {
  /** 预置的任务过滤条件（从任务列表跳转过来时） */
  task?: string;
}

const TaskLogTable: React.FC<TaskLogTableProps> = ({ task }) => {
  const t = useT();
  const logStatusEnum = useTaskLogStatusEnum();

  const columns: ProColumns<TaskLog>[] = [
    { title: t('pages.monitor.tasks.task'), dataIndex: 'task', copyable: true },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: logStatusEnum,
    },
    {
      title: t('pages.monitor.tasks.duration'),
      dataIndex: 'duration_ms',
      search: false,
      sorter: true,
      renderText: (value: number | null) =>
        value === null ? '-' : `${value} ms`,
    },
    {
      title: t('pages.monitor.tasks.startedAt'),
      dataIndex: 'started_at',
      valueType: 'dateTime',
      width: 170,
      search: false,
      sorter: true,
    },
    {
      title: t('pages.monitor.tasks.finishedAt'),
      dataIndex: 'finished_at',
      valueType: 'dateTime',
      width: 170,
      search: false,
    },
  ];

  return (
    <ProTable<TaskLog>
      rowKey="id"
      columns={columns}
      form={{ initialValues: { task } }}
      request={tableRequest(queryTaskLogs)}
      expandable={{
        rowExpandable: (record) => Boolean(record.output),
        expandedRowRender: (record) => (
          <Typography.Paragraph
            style={{ whiteSpace: 'pre-wrap', marginBottom: 0 }}
            copyable
          >
            {record.output}
          </Typography.Paragraph>
        ),
      }}
    />
  );
};

export default TaskLogTable;
