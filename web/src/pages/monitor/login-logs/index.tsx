import { ClearOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { App, Button, Popconfirm } from 'antd';
import type React from 'react';
import { useRef } from 'react';
import { DeleteAction } from '@/components';
import { useT } from '@/utils/i18n';
import { createdRangeTransform, tableRequest } from '@/utils/table';
import type { LoginLog } from './data';
import { clearLoginLogs, deleteLoginLog, queryLoginLogs } from './service';

const LoginLogList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);
  const canDelete = access.can('monitor.login-log.delete');

  const reload = () => actionRef.current?.reload();

  const handleClear = async () => {
    await clearLoginLogs();
    message.success(t('common.cleared'));
    reload();
  };

  const columns: ProColumns<LoginLog>[] = [
    { title: t('pages.monitor.loginLogs.username'), dataIndex: 'username' },
    { title: t('pages.monitor.loginLogs.ip'), dataIndex: 'ip' },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: {
        success: {
          text: t('pages.monitor.loginLogs.status.success'),
          status: 'Success',
        },
        failed: {
          text: t('pages.monitor.loginLogs.status.failed'),
          status: 'Error',
        },
      },
    },
    {
      title: t('pages.monitor.loginLogs.message'),
      dataIndex: 'message',
      search: false,
      ellipsis: true,
    },
    {
      title: t('pages.monitor.loginLogs.userAgent'),
      dataIndex: 'user_agent',
      search: false,
      ellipsis: true,
    },
    {
      title: t('pages.monitor.loginLogs.loginAt'),
      dataIndex: 'created_at',
      valueType: 'dateTime',
      width: 170,
      search: false,
      sorter: true,
    },
    {
      title: t('pages.monitor.loginLogs.loginAt'),
      dataIndex: 'created_range',
      valueType: 'dateRange',
      hideInTable: true,
      search: { transform: createdRangeTransform },
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 80,
      hideInTable: !canDelete,
      render: (_, record) => [
        <DeleteAction
          key="delete"
          onConfirm={() => deleteLoginLog(record.id)}
          onSuccess={reload}
        />,
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<LoginLog>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryLoginLogs)}
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
    </PageContainer>
  );
};

export default LoginLogList;
