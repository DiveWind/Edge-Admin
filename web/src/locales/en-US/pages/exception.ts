import type zh from '../../zh-CN/pages/exception';

const messages: Record<keyof typeof zh, string> = {
  'pages.exception.403': 'Sorry, you are not authorized to access this page.',
  'pages.exception.404': 'Sorry, the page you visited does not exist.',
  'pages.exception.500': 'Sorry, something went wrong on the server.',
  'pages.exception.backHome': 'Back Home',
};

export default messages;
