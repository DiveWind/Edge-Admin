import type zh from '../zh-CN/app';

const messages: Record<keyof typeof zh, string> = {
  'app.request.401': 'Your session has expired. Please log in again.',
  'app.request.403': 'You are not allowed to perform this action.',
  'app.request.422': 'The submitted data is invalid.',
  'app.request.429': 'Too many requests. Please try again later.',
  'app.request.status': 'Request failed (HTTP {status})',
  'app.request.offline':
    'Network unavailable. Please check your connection and try again.',
  'app.request.noResponse': 'No response from server. Please try again later.',
  'app.request.unknown': 'Request error. Please try again later.',
  'app.network.offline':
    'You are currently offline. Some features may be unavailable.',
  'app.error.chunk.title': 'Failed to load page',
  'app.error.chunk.description.offline':
    'Your network connection has been lost. Please check your connection and reload.',
  'app.error.chunk.description.online':
    'Page resources failed to load. Please reload and try again.',
  'app.error.render.title': 'Something went wrong',
  'app.error.render.description':
    'Sorry, an error occurred on this page. Please reload or go back to the home page.',
  'app.error.retry': 'Retry',
  'app.error.reload': 'Reload Page',
  'app.error.home': 'Back Home',
  'app.header.language': 'Language',
  'app.header.accountSettings': 'Account Settings',
  'app.header.logout': 'Log out',
  'app.notice.title': 'Notifications',
  'app.notice.empty': 'No unread notifications',
  'app.notice.markRead': 'Read',
  'app.notice.markAllRead': 'Mark all as read',
  'app.notice.viewAll': 'View all',
  'app.header.theme.toDark': 'Switch to dark theme',
  'app.header.theme.toLight': 'Switch to light theme',
};

export default messages;
