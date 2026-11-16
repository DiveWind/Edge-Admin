# Changelog

本项目的所有重要变更都会记录在此文件中。

格式基于 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

### Added

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
