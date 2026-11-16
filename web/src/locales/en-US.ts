import app from './en-US/app';
import common from './en-US/common';
import component from './en-US/component';
import menu from './en-US/menu';
import account from './en-US/pages/account';
import content from './en-US/pages/content';
import dashboard from './en-US/pages/dashboard';
import exception from './en-US/pages/exception';
import login from './en-US/pages/login';
import message from './en-US/pages/message';
import monitor from './en-US/pages/monitor';
import system from './en-US/pages/system';
import permission from './en-US/permission';
// edge:locale-imports

/** 各模块已用 zh-CN 的 key 约束，漏翻会在 tsc 阶段报错 */
export default {
  ...app,
  ...common,
  ...component,
  ...menu,
  ...permission,
  ...login,
  ...exception,
  ...dashboard,
  ...system,
  ...monitor,
  ...message,
  ...content,
  ...account,
  // edge:locale-spread
};
