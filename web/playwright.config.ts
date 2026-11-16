import { defineConfig, devices } from '@playwright/test';

/**
 * 端到端测试（真实后端，不 mock）。前置条件：
 * 1. 后端已启动并 seed：`cd ../server && php artisan migrate:fresh --seed && php artisan serve --port=8000`
 * 2. 队列已启动（公告通知用例需要）：`cd ../server && php artisan queue:work`
 * 3. 前端开发服务器：未运行时由下方 webServer 自动启动（已运行则复用）
 *
 * 用例会写入数据（均带随机后缀并在用例内清理），建议在本地 / 测试库运行；不在 CI 必过项中。
 * @doc https://playwright.dev/docs/test-configuration
 */
const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8001';

export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  // 共享同一个后端与账号，串行执行更稳定
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [['list']],
  use: {
    ...devices['Desktop Chrome'],
    baseURL,
    viewport: { width: 1440, height: 900 },
    locale: 'zh-CN',
    // 与后端 APP_TIMEZONE 一致，日期时间断言才稳定
    timezoneId: 'Asia/Shanghai',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: process.env.E2E_BASE_URL
    ? undefined
    : {
        command: 'npm run dev',
        url: baseURL,
        reuseExistingServer: true,
        timeout: 180_000,
      },
});
