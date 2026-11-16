import type zh from '../../zh-CN/pages/dashboard';

const messages: Record<keyof typeof zh, string> = {
  'pages.dashboard.welcome': 'Hello {name}, welcome back.',
  'pages.dashboard.totals.users': 'Users',
  'pages.dashboard.totals.roles': 'Roles',
  'pages.dashboard.totals.departments': 'Departments',
  'pages.dashboard.totals.articles': 'Articles',
  'pages.dashboard.totals.todayLogins': 'Logins Today',
  'pages.dashboard.totals.todayOperations': 'Operations Today',
  'pages.dashboard.totals.onlineSessions': 'Online Sessions',
  'pages.dashboard.totals.attachments': 'Attachments',
  'pages.dashboard.loginTrend': 'Logins in the Last 14 Days',
  'pages.dashboard.login.success': 'Success',
  'pages.dashboard.login.failed': 'Failed',
  'pages.dashboard.articlesByCategory': 'Articles by Category',
  'pages.dashboard.usersByDepartment': 'Users by Department',
  'pages.dashboard.recentOperations': 'Recent Operations',
  'pages.dashboard.operations.user': 'User',
  'pages.dashboard.operations.action': 'Action',
  'pages.dashboard.operations.statusCode': 'Status',
  'pages.dashboard.operations.time': 'Time',
};

export default messages;
