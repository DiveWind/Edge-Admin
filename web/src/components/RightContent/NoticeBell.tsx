import { BellOutlined } from '@ant-design/icons';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { history, useModel } from '@umijs/max';
import {
  Badge,
  Button,
  Empty,
  Flex,
  Listy,
  Popover,
  Spin,
  Typography,
} from 'antd';
import { createStyles } from 'antd-style';
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

const useStyles = createStyles(({ token, css }) => ({
  panel: css`
    width: 360px;
    max-width: calc(100vw - ${token.marginLG}px);
  `,
  item: css`
    padding: ${token.paddingXS}px 0;
    border-bottom: 1px solid ${token.colorSplit};
  `,
  footer: css`
    padding-top: ${token.paddingXS}px;
  `,
}));

/** 头部通知铃铛：未读数 + 最近未读；任何通知变更后 invalidate notificationKeys.all 统一刷新 */
export const NoticeBell: React.FC = () => {
  const t = useT();
  const { styles: actionStyles } = useHeaderActionStyles();
  const { styles } = useStyles();
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

  const renderItem = (item: API.Notification) => (
    <Flex className={styles.item} justify="space-between" gap="small">
      <Flex vertical style={{ minWidth: 0 }}>
        <Typography.Text strong ellipsis>
          {item.title}
        </Typography.Text>
        <Typography.Text type="secondary" ellipsis>
          {item.content}
        </Typography.Text>
        <Typography.Text type="secondary">
          {dayjs(item.created_at).fromNow()}
        </Typography.Text>
      </Flex>
      <Button
        type="link"
        size="small"
        onClick={() => handleRead(item.id).catch(() => undefined)}
      >
        {t('app.notice.markRead')}
      </Button>
    </Flex>
  );

  const content = (
    <Flex vertical className={styles.panel}>
      <Spin spinning={isFetching}>
        {latest.length ? (
          <Listy<API.Notification>
            items={latest}
            rowKey="id"
            itemRender={renderItem}
          />
        ) : (
          <Empty
            image={Empty.PRESENTED_IMAGE_SIMPLE}
            description={t('app.notice.empty')}
          />
        )}
      </Spin>
      <Flex justify="space-between" className={styles.footer}>
        <Button
          type="link"
          size="small"
          disabled={!unreadCount}
          onClick={() => handleReadAll().catch(() => undefined)}
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
        className={actionStyles.action}
        aria-label={t('app.notice.title')}
      >
        <Badge count={unreadCount} size="small">
          <BellOutlined />
        </Badge>
      </Button>
    </Popover>
  );
};
