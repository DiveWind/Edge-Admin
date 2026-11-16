import app from './zh-CN/app';
import common from './zh-CN/common';
import component from './zh-CN/component';
import menu from './zh-CN/menu';
import account from './zh-CN/pages/account';
import content from './zh-CN/pages/content';
import dashboard from './zh-CN/pages/dashboard';
import exception from './zh-CN/pages/exception';
import login from './zh-CN/pages/login';
import message from './zh-CN/pages/message';
import monitor from './zh-CN/pages/monitor';
import system from './zh-CN/pages/system';

/** 默认语言，也是文案 key 的类型来源（见 src/utils/i18n.ts） */
export default {
  ...app,
  ...common,
  ...component,
  ...menu,
  ...login,
  ...exception,
  ...dashboard,
  ...system,
  ...monitor,
  ...message,
  ...content,
  ...account,
};
