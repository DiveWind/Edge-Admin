import { CheckOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useQueryClient } from '@tanstack/react-query';
import { App, Badge, Button, Typography } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import {
  deleteNotification,
  markAllNotificationsRead,
  markNotificationRead,
  notificationKeys,
  queryNotifications,
} from '@/services/notification';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';

type Filter = 'all' | 'unread';

/** 我的通知：只要求登录 */
const NotificationList: React.FC = () => {
  const t = useT();
  const { message } = App.useApp();
  const queryClient = useQueryClient();
  const actionRef = useRef<ActionType>(null);
  const [filter, setFilter] = useState<Filter>('all');

  /** 刷新列表，并让头部铃铛的未读数同步 */
  const refresh = () => {
    actionRef.current?.reload();
    queryClient.invalidateQueries({ queryKey: notificationKeys.all });
  };

  const handleRead = async (id: string) => {
    await markNotificationRead(id);
    refresh();
  };

  const handleReadAll = async () => {
    await markAllNotificationsRead();
    message.success(t('pages.message.notifications.allRead'));
    refresh();
  };

  const columns: ProColumns<API.Notification>[] = [
    {
      title: t('pages.message.notifications.title'),
      dataIndex: 'title',
      render: (_, record) => (
        <Badge dot={!record.read_at} offset={[6, 0]}>
          <Typography.Text strong={!record.read_at}>
            {record.title}
          </Typography.Text>
        </Badge>
      ),
    },
    {
      title: t('pages.message.notifications.content'),
      dataIndex: 'content',
      ellipsis: true,
      width: 360,
    },
    {
      title: t('pages.message.notifications.receivedAt'),
      dataIndex: 'created_at',
      valueType: 'dateTime',
      width: 180,
      sorter: true,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 140,
      render: (_, record) => [
        !record.read_at && (
          <Typography.Link
            key="read"
            onClick={() => handleRead(record.id).catch(() => undefined)}
          >
            {t('pages.message.notifications.markRead')}
          </Typography.Link>
        ),
        <DeleteAction
          key="delete"
          onConfirm={() => deleteNotification(record.id)}
          onSuccess={refresh}
        />,
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<API.Notification>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        search={false}
        params={{ read: filter === 'unread' ? 0 : undefined }}
        request={tableRequest(queryNotifications)}
        expandable={{
          expandedRowRender: (record) => (
            <Typography.Paragraph
              style={{ whiteSpace: 'pre-wrap', marginBottom: 0 }}
            >
              {record.content}
            </Typography.Paragraph>
          ),
        }}
        toolbar={{
          menu: {
            type: 'tab',
            activeKey: filter,
            items: [
              { key: 'all', label: t('pages.message.notifications.all') },
              { key: 'unread', label: t('pages.message.notifications.unread') },
            ],
            onChange: (key) => setFilter(key as Filter),
          },
          actions: [
            <Button
              key="read-all"
              icon={<CheckOutlined />}
              onClick={() => handleReadAll().catch(() => undefined)}
            >
              {t('pages.message.notifications.markAllRead')}
            </Button>,
          ],
        }}
      />
    </PageContainer>
  );
};

export default NotificationList;
