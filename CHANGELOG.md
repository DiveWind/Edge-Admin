# Changelog

本项目的所有重要变更都会记录在此文件中。

格式基于 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

### Added

- 动态菜单权限：`permissions` 升级为目录 / 菜单 / 按钮三级菜单树（`path`、`icon`、`is_external`、`is_visible`、`status`、`is_system`、`remark`）；
  菜单管理接口 `POST/GET/PUT/DELETE /api/permissions[/{id}]`（层级校验、防成环、内置节点保护、删除时解除角色授权）与权限码
  `system.permission.create/update/delete`；当前用户菜单 `GET /api/auth/menus`；停用节点（含祖先停用）的权限对所有人不生效。
- 前端：侧边菜单改为服务端下发（`menuDataRender`，名称 / 图标 / 顺序 / 显隐以服务端为准，外链新窗口打开，登录与保存后即时刷新）；
  菜单管理页（树形表格、按类型联动的表单、`IconPicker` 图标选择器、显示 / 状态快捷切换）；角色授权树区分目录 / 菜单 / 按钮；
  `/` 落地页按服务端菜单跳转；失败队列拆为独立页面 `/monitor/failed-jobs`（对应 `monitor.failed-job` 菜单）。
- 菜单「我的通知」（`message.notification`）。

### Changed

- `permission:sync` 只新增缺失的内置节点并修正 `type` / `path` / `is_external`，不覆盖界面上修改过的名称 / 图标 / 排序 / 显示 / 状态 / 上级，
  不再删除任何节点（已从配置移除的内置节点转为自定义节点）。
- 代码生成器登记的菜单节点带 `type` / `path` / `icon`，sync 后自动出现在动态菜单中；不再修改前端 `menu.ts`（移除 `web/menu_item`、`web/menu_module` 模板与 `// edge:menu` 锚点）。
- 菜单 / 权限名称 i18n：后端 `name` 被管理员改过时，所有语言下直接显示 `name`。

- 认证：Sanctum API Token 登录 / 退出、个人资料、修改密码（吊销其他设备 token）、登录限流与登录日志。
- 系统管理：用户、角色、权限（代码声明 + `permission:sync`）、部门（树）、岗位。
- 权限：原生 Gate / Policy / `can:` 的功能权限；五种数据权限（全部 / 自定义部门 / 本部门及下级 / 本部门 / 本人），多角色取并集。
- 数据字典：字典类型与字典项管理、`GET /api/options/dicts`（带缓存）、`Dictionary` 服务与 `InDict` 校验规则。
- 系统参数：类型化参数（string / number / boolean / json）、内置参数保护、免登录的公开参数接口、`setting()` 辅助函数；
  登录限流次数与日志保留天数可在线调整。
- 附件管理：上传即登记附件、可配置的类型与大小、附件列表与删除（连同物理文件）。
- 系统监控：登录日志、操作日志（自动记录、脱敏、双语摘要）、在线会话（强制下线）、系统信息、定时任务（查看 / 立即执行 / 执行日志）、失败队列。
- 消息：通知公告（发布后通过队列发送站内通知）、我的通知。
- 内容示例模块：文章分类、文章（数据权限、字典字段 `source`）。
- 工作台统计。
- 多语言：按 `Accept-Language` 返回中文 / 英文提示，统一的 401 / 403 / 404 / 405 / 422 / 429 JSON 错误。
- 代码生成器 `php artisan edge:make-module`：字段 DSL、可定制 stub、幂等登记权限点 / 路由 / 操作日志 / 前端路由与文案。
- 工程化：Pest 架构测试、Larastan level 8、Pint、GitHub Actions CI、可选的 Laravel Sail、完整中文文档。
- 代码生成器：字段规则变量（`field.required` / `field.nullable` / `field.max` 等）与前端校验、条件块 `{{#if}} … {{else}} … {{/if}}`、
  TS 导入声明 `{{@import …}}`（按实际使用汇总去重，生成的 TSX 无未使用导入）、生成后自动用前端的 Biome 格式化、
  表单拆到 `components/<Class>Form.tsx`、`--with-options` 生成 `GET /api/options/<资源>`、外键下拉自动接入已有选项接口
  （前端通用函数 `fetchSelectOptions()`）、`--data-scope` 时前端类型与部门 / 创建人列、datetime 字段的时区断言测试。

### Fixed

- 带时区偏移的日期时间输入（如 `2026-11-16T10:30:00+00:00`）不再丢失偏移：所有模型使用 `StoresDatesInAppTimezone`
  在写库前换算到 `APP_TIMEZONE`，`created_from` / `created_to` 等时间范围过滤同样换算；前端日期时间控件改为提交带偏移的 ISO 8601（`toOffsetDateTime()`）。
- 登录限流改为与官方 starter kit 一致：只累计失败次数、登录成功后清零（`LoginRequest::authenticate()`），仍返回 429 + `Retry-After`，
  次数仍由系统参数 `security.login_max_attempts` 控制。
