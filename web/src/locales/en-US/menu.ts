import type zh from '../zh-CN/menu';

const messages: Record<keyof typeof zh, string> = {
  'menu.login': 'Login',
  'menu.dashboard': 'Dashboard',
  'menu.system': 'System',
  'menu.system.users': 'Users',
  'menu.system.roles': 'Roles',
  'menu.system.permissions': 'Permissions',
  'menu.system.departments': 'Departments',
  'menu.system.positions': 'Positions',
  'menu.system.dicts': 'Dictionaries',
  'menu.system.settings': 'Settings',
  'menu.system.attachments': 'Attachments',
  'menu.monitor': 'Monitor',
  'menu.monitor.login-logs': 'Login Logs',
  'menu.monitor.operation-logs': 'Operation Logs',
  'menu.monitor.tasks': 'Tasks',
  'menu.monitor.online-sessions': 'Online Sessions',
  'menu.monitor.system-info': 'System Info',
  'menu.message': 'Messages',
  'menu.message.announcements': 'Announcements',
  'menu.message.notifications': 'My Notifications',
  'menu.content': 'Content',
  'menu.content.articles': 'Articles',
  'menu.content.categories': 'Categories',
  'menu.account-settings': 'Account Settings',
  // edge:menu
};

export default messages;
