/**
 * 权限点 / 菜单名称（角色授权树、权限管理页按 code 翻译，缺失时回退后端 name）。
 * 查找顺序：`permission.<code>` → 操作类权限 `permission.action.<最后一段>` → 后端 name。
 * 新增模块时补菜单级 code 即可；常规操作（view/create/update/delete）走通用文案。
 */
export default {
  'permission.action.view': '查看',
  'permission.action.create': '新增',
  'permission.action.update': '编辑',
  'permission.action.delete': '删除',
  'permission.action.reset-password': '重置密码',
  'permission.action.run': '立即执行',
  'permission.action.retry': '重试',
  'permission.action.publish': '发布',
  'permission.dashboard': '工作台',
  'permission.system': '系统管理',
  'permission.system.user': '用户管理',
  'permission.system.role': '角色管理',
  'permission.system.permission': '权限管理',
  'permission.system.department': '部门管理',
  'permission.system.position': '岗位管理',
  'permission.system.dict': '数据字典',
  'permission.system.setting': '系统参数',
  'permission.system.attachment': '附件管理',
  'permission.monitor': '系统监控',
  'permission.monitor.login-log': '登录日志',
  'permission.monitor.operation-log': '操作日志',
  'permission.monitor.scheduled-task': '定时任务',
  'permission.monitor.failed-job': '失败队列',
  'permission.monitor.online-session': '在线会话',
  'permission.monitor.online-session.delete': '强制下线',
  'permission.monitor.system-info': '系统信息',
  'permission.message': '消息中心',
  'permission.message.announcement': '通知公告',
  'permission.content': '内容管理',
  'permission.content.category': '文章分类',
  'permission.content.article': '文章管理',
};
