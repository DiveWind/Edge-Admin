import { BellOutlined } from '@ant-design/icons';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { history, useModel } from '@umijs/max';
import { Badge, Button, Flex, List, Popover, Typography } from 'antd';
import dayjs from 'dayjs';
import type React from 'react';
import { useState } from 'react';
import {
  getUnreadCount,
  markAllNotificationsRead,
  markNotificationRead,
  notificationKeys,
  queryNotifications,
} from '@/services/notification';
import { useT } from '@/utils/i18n';
import useHeaderActionStyles from './style';

/** 未读数轮询间隔 */
const POLL_INTERVAL = 60_000;
const LATEST_SIZE = 5;

/** 头部通知铃铛：未读数 + 最近未读，变更后 invalidate notificationKeys.all 统一刷新 */
export const NoticeBell: React.FC = () => {
  const t = useT();
  const { styles } = useHeaderActionStyles();
  const { initialState } = useModel('@@initialState');
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const loggedIn = Boolean(initialState?.currentUser);

  const { data: unreadCount = 0 } = useQuery({
    queryKey: notificationKeys.unreadCount,
    queryFn: async () => (await getUnreadCount()).data.count,
    enabled: loggedIn,
    refetchInterval: POLL_INTERVAL,
  });

  const { data: latest = [], isFetching } = useQuery({
    queryKey: notificationKeys.latestUnread,
    queryFn: async () =>
      (await queryNotifications({ read: 0, per_page: LATEST_SIZE })).data,
    enabled: loggedIn && open,
  });

  const refresh = () =>
    queryClient.invalidateQueries({ queryKey: notificationKeys.all });

  const handleRead = async (id: string) => {
    await markNotificationRead(id);
    await refresh();
  };

  const handleReadAll = async () => {
    await markAllNotificationsRead();
    await refresh();
  };

  const goToCenter = () => {
    setOpen(false);
    history.push('/message/notifications');
  };

  const content = (
    <Flex vertical gap="small" style={{ width: 360 }}>
      <List<API.Notification>
        size="small"
        loading={isFetching}
        dataSource={latest}
        locale={{ emptyText: t('app.notice.empty') }}
        renderItem={(item) => (
          <List.Item
            actions={[
              <Button
                key="read"
                type="link"
                size="small"
                onClick={() => handleRead(item.id)}
              >
                {t('app.notice.markRead')}
              </Button>,
            ]}
          >
            <List.Item.Meta
              title={<Typography.Text ellipsis>{item.title}</Typography.Text>}
              description={
                <Flex vertical>
                  <Typography.Text type="secondary" ellipsis>
                    {item.content}
                  </Typography.Text>
                  <Typography.Text type="secondary">
                    {dayjs(item.created_at).fromNow()}
                  </Typography.Text>
                </Flex>
              }
            />
          </List.Item>
        )}
      />
      <Flex justify="space-between">
        <Button
          type="link"
          size="small"
          disabled={!unreadCount}
          onClick={handleReadAll}
        >
          {t('app.notice.markAllRead')}
        </Button>
        <Button type="link" size="small" onClick={goToCenter}>
          {t('app.notice.viewAll')}
        </Button>
      </Flex>
    </Flex>
  );

  return (
    <Popover
      title={t('app.notice.title')}
      content={content}
      trigger="click"
      placement="bottomRight"
      open={open}
      onOpenChange={setOpen}
    >
      <Button
        type="text"
        className={styles.action}
        aria-label={t('app.notice.title')}
      >
        <Badge count={unreadCount} size="small">
          <BellOutlined />
        </Badge>
      </Button>
    </Popover>
  );
};
