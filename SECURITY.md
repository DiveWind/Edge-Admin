# 安全策略

## 支持的版本

| 版本 | 是否提供安全修复 |
|---|---|
| `main` / 最新发布版本 | ✅ |
| 更早的版本 | ❌ |

## 报告漏洞

**请不要通过公开的 Issue、讨论区或 PR 报告安全漏洞。**

请使用 GitHub 仓库的 **Security → Report a vulnerability**（私密安全通告）提交，内容尽量包括：

- 受影响的版本 / 提交、组件（`server` / `web`）；
- 复现步骤或概念验证；
- 影响评估（如越权、信息泄露、XSS、注入）。

我们会在 3 个工作日内确认收到，并在修复发布后致谢报告者（如你愿意署名）。

## 部署方的安全建议

- 生产环境 `APP_DEBUG=false`，修改或删除演示账号（默认密码为 `password`）；
- 上传类型由 `EDGE_UPLOAD_EXTENSIONS` 控制，不要加入 `svg`、`html` 等可执行脚本的类型；
- 位于反向代理之后时只信任明确的代理 IP（`trustProxies`）；
- 定期执行 `composer audit` 与 `npm audit`。
