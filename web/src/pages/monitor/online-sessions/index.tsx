import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { Space, Tag, Tooltip } from 'antd';
import dayjs from 'dayjs';
import type React from 'react';
import { useRef } from 'react';
import { DeleteAction } from '@/components';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import type { OnlineSession } from './data';
import { kickOnlineSession, queryOnlineSessions } from './service';

/** 在线会话：当前会话带标记且不可强制下线 */
const OnlineSessionList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const actionRef = useRef<ActionType>(null);
  const canKick = access.can('monitor.online-session.delete');

  const columns: ProColumns<OnlineSession>[] = [
    {
      title: t('pages.monitor.onlineSessions.user'),
      dataIndex: ['user', 'name'],
      search: false,
      render: (_, record) => (
        <Space size={4}>
          {record.user?.name ?? '-'}
          {record.is_current && (
            <Tag color="processing">
              {t('pages.monitor.onlineSessions.current')}
            </Tag>
          )}
        </Space>
      ),
    },
    {
      title: t('pages.monitor.onlineSessions.username'),
      dataIndex: 'username',
      width: 120,
      render: (_, record) => record.user?.username ?? '-',
    },
    {
      title: t('pages.monitor.onlineSessions.ip'),
      dataIndex: 'ip',
      width: 140,
    },
    {
      title: t('pages.monitor.onlineSessions.userAgent'),
      dataIndex: 'user_agent',
      search: false,
      ellipsis: true,
      width: 220,
    },
    {
      title: t('pages.monitor.onlineSessions.lastUsedAt'),
      dataIndex: 'last_used_at',
      search: false,
      sorter: true,
      width: 140,
      render: (_, record) =>
        record.last_used_at ? (
          <Tooltip
            title={dayjs(record.last_used_at).format('YYYY-MM-DD HH:mm:ss')}
          >
            {dayjs(record.last_used_at).fromNow()}
          </Tooltip>
        ) : (
          t('pages.monitor.onlineSessions.never')
        ),
    },
    {
      title: t('pages.monitor.onlineSessions.loginAt'),
      dataIndex: 'created_at',
      valueType: 'dateTime',
      search: false,
      sorter: true,
      width: 170,
    },
    {
      title: t('pages.monitor.onlineSessions.expiresAt'),
      dataIndex: 'expires_at',
      key: 'expires_at',
      valueType: 'dateTime',
      search: false,
      width: 170,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 100,
      hideInTable: !canKick,
      render: (_, record) => [
        <DeleteAction
          key="kick"
          disabled={record.is_current}
          title={t('pages.monitor.onlineSessions.kickConfirm')}
          successMessage={t('pages.monitor.onlineSessions.kicked')}
          onConfirm={() => kickOnlineSession(record.id)}
          onSuccess={() => actionRef.current?.reload()}
        >
          {t('pages.monitor.onlineSessions.kick')}
        </DeleteAction>,
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<OnlineSession>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryOnlineSessions)}
        columnsState={{ defaultValue: { expires_at: { show: false } } }}
      />
    </PageContainer>
  );
};

export default OnlineSessionList;
