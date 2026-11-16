import { ClearOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { App, Button, Popconfirm, Tag, Typography } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import { useT } from '@/utils/i18n';
import { createdRangeTransform, tableRequest } from '@/utils/table';
import OperationLogDetail from './components/OperationLogDetail';
import StatusCodeTag from './components/StatusCodeTag';
import type { OperationLog } from './data';
import {
  clearOperationLogs,
  deleteOperationLog,
  HTTP_METHODS,
  methodColor,
  queryOperationLogs,
} from './service';

const OperationLogList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);
  const [detailId, setDetailId] = useState<API.Id>();
  const canDelete = access.can('monitor.operation-log.delete');

  const reload = () => actionRef.current?.reload();

  const handleClear = async () => {
    await clearOperationLogs();
    message.success(t('common.cleared'));
    reload();
  };

  const columns: ProColumns<OperationLog>[] = [
    {
      title: t('pages.monitor.operationLogs.user'),
      dataIndex: ['user', 'name'],
      search: false,
    },
    {
      title: t('pages.monitor.operationLogs.username'),
      dataIndex: 'username',
      hideInTable: true,
    },
    {
      title: t('pages.monitor.operationLogs.summary'),
      dataIndex: 'summary',
      search: false,
      ellipsis: true,
      width: 160,
    },
    {
      title: t('pages.monitor.operationLogs.method'),
      dataIndex: 'method',
      valueType: 'select',
      valueEnum: Object.fromEntries(HTTP_METHODS.map((m) => [m, { text: m }])),
      render: (_, record) => (
        <Tag color={methodColor[record.method]}>{record.method}</Tag>
      ),
    },
    {
      title: t('pages.monitor.operationLogs.path'),
      dataIndex: 'path',
      ellipsis: true,
      width: 240,
    },
    {
      title: t('pages.monitor.operationLogs.statusCode'),
      dataIndex: 'status_code',
      valueType: 'digit',
      render: (_, record) => <StatusCodeTag code={record.status_code} />,
    },
    {
      title: t('pages.monitor.operationLogs.duration'),
      dataIndex: 'duration_ms',
      search: false,
      sorter: true,
      renderText: (value: number) => `${value} ms`,
    },
    {
      title: t('pages.monitor.operationLogs.ip'),
      dataIndex: 'ip',
      search: false,
    },
    {
      title: t('pages.monitor.operationLogs.operatedAt'),
      dataIndex: 'created_at',
      valueType: 'dateTime',
      width: 170,
      search: false,
      sorter: true,
    },
    {
      title: t('pages.monitor.operationLogs.operatedAt'),
      dataIndex: 'created_range',
      valueType: 'dateRange',
      hideInTable: true,
      search: { transform: createdRangeTransform },
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 120,
      render: (_, record) => [
        <Typography.Link key="detail" onClick={() => setDetailId(record.id)}>
          {t('common.detail')}
        </Typography.Link>,
        canDelete && (
          <DeleteAction
            key="delete"
            onConfirm={() => deleteOperationLog(record.id)}
            onSuccess={reload}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<OperationLog>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryOperationLogs)}
        toolBarRender={() => [
          canDelete && (
            <Popconfirm
              key="clear"
              title={t('common.clearConfirm')}
              onConfirm={() => handleClear().catch(() => undefined)}
            >
              <Button danger icon={<ClearOutlined />}>
                {t('common.clear')}
              </Button>
            </Popconfirm>
          ),
        ]}
      />
      <OperationLogDetail
        id={detailId}
        onClose={() => setDetailId(undefined)}
      />
    </PageContainer>
  );
};

export default OperationLogList;
