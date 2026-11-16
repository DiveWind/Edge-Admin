import type zh from '../../zh-CN/pages/message';

const messages: Record<keyof typeof zh, string> = {
  'pages.message.announcements.create': 'New Announcement',
  'pages.message.announcements.edit': 'Edit Announcement',
  'pages.message.announcements.view': 'View Announcement',
  'pages.message.announcements.title': 'Title',
  'pages.message.announcements.type': 'Type',
  'pages.message.announcements.type.notice': 'Notice',
  'pages.message.announcements.type.announcement': 'Announcement',
  'pages.message.announcements.content': 'Content',
  'pages.message.announcements.publish': 'Publish',
  'pages.message.announcements.publishConfirm':
    'All active users will be notified and it can no longer be edited. Publish now?',
  'pages.message.announcements.published':
    'Published. Notifications are being sent.',
  'pages.message.notifications.all': 'All',
  'pages.message.notifications.unread': 'Unread',
  'pages.message.notifications.title': 'Title',
  'pages.message.notifications.content': 'Content',
  'pages.message.notifications.receivedAt': 'Time',
  'pages.message.notifications.markRead': 'Mark as read',
  'pages.message.notifications.markAllRead': 'Mark all as read',
  'pages.message.notifications.allRead': 'All notifications marked as read',
};

export default messages;
