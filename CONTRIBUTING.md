# 贡献指南

感谢你愿意改进 Edge Admin！提交前请花几分钟阅读本文。

## 开始之前

- 先读 [docs/README.md](docs/README.md)：接口契约 [architecture.md](docs/architecture.md) 是前后端的唯一依据，后端规范见
  [backend-guide.md](docs/backend-guide.md)。
- 较大的改动（新模块、契约变更、新增依赖）请先开 Issue 讨论。项目原则上只使用 Laravel / Ant Design 官方生态的依赖。
- 安全问题请不要公开提 Issue，见 [SECURITY.md](SECURITY.md)。

## 分支与提交

- 从 `main` 切出分支：`feat/<简述>`、`fix/<简述>`、`docs/<简述>`、`refactor/<简述>`、`chore/<简述>`。
- 提交信息遵循 [Conventional Commits](https://www.conventionalcommits.org/zh-hans/)：

  ```text
  <type>(<scope>): <subject>

  feat(server): 新增数据字典模块
  fix(web): 修复用户列表分页参数
  docs: 补充部署文档的队列配置
  ```

  常用 type：`feat` `fix` `docs` `style` `refactor` `perf` `test` `build` `ci` `chore` `revert`；scope 建议 `server`、`web`、`docs`
  或模块名。破坏性变更在正文写 `BREAKING CHANGE: ...`。

## 本地检查

后端（`server/`）：

```bash
composer lint       # Pint（可先 composer format 自动修复）
composer analyse    # Larastan level 8，零错误，不新增 baseline / ignore
composer test       # Pest：架构测试 + Feature + Unit
```

前端（`web/`）：

```bash
npm run lint        # Biome + tsc
npm run build
```

## Pull Request 要求

- 一个 PR 只做一件事，描述中说明动机与影响；关联 Issue。
- 改动接口（路径、字段、权限码、状态码）**必须同步修改 `docs/architecture.md`**，并在 PR 描述中列出。
- 行为变更需附带测试；新模块参考 [代码生成器](docs/generator.md) 与文章模块。
- 用户可见文案同时提供 `zh_CN` 与 `en`。
- 在 [CHANGELOG.md](CHANGELOG.md) 的 `[Unreleased]` 下记录变更。
- CI（Pint、Larastan、Pest、前端 lint + build）全部通过后再请求评审。
