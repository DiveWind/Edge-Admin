/**
 * 菜单 / 权限点名称（侧边菜单、菜单管理页、角色授权树按 code 翻译，见 src/utils/permission.ts）。
 * 查找顺序：`permission.<code>` → 操作类权限 `permission.action.<最后一段>` → 后端 name。
 * 只有名称未被管理员修改（后端 name 等于这里的中文文案）时才使用翻译，改过名或自定义的节点直接显示后端 name，
 * 因此这里的中文须与 server/config/permissions.php 的 name 保持一致。新增模块时补菜单级 code 即可。
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
  'permission.system.permission': '菜单管理',
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
  'permission.message.notification': '我的通知',
  'permission.content': '内容管理',
  'permission.content.category': '文章分类',
  'permission.content.article': '文章管理',
};
