import type zh from '../../zh-CN/pages/login';

const messages: Record<keyof typeof zh, string> = {
  'pages.login.subTitle':
    'RBAC admin scaffold built on Laravel + Ant Design Pro',
  'pages.login.hint': 'Demo account: admin / password',
  'pages.login.username.placeholder': 'Username',
  'pages.login.username.required': 'Please enter your username',
  'pages.login.password.placeholder': 'Password',
  'pages.login.password.required': 'Please enter your password',
  'pages.login.success': 'Logged in successfully',
};

export default messages;
