import { join } from 'node:path';
import type { Locator, Page } from '@playwright/test';
import { test as base, expect } from '@playwright/test';

export const AUTH_DIR = join(__dirname, '.auth');
export const ROLE_USERS = ['admin', 'editor', 'viewer', 'marketing'] as const;
export type RoleUser = (typeof ROLE_USERS)[number];

export const storageStatePath = (user: RoleUser) =>
  join(AUTH_DIR, `${user}.json`);

export const FORBIDDEN_TEXT = '抱歉，你无权访问该页面';

/** 本次运行的随机后缀，写入的数据都带上它，便于识别与清理 */
export const RUN_ID = Date.now().toString(36).slice(-5);

/**
 * 在默认 test 上加一个自动 fixture：收集页面运行时错误与 5xx 响应，用例结束时断言为空。
 * 4xx 属于业务流程（422 回填、403 提示），由各用例自行断言。
 */
export const test = base.extend<{ pageProblems: string[] }>({
  pageProblems: [
    async ({ page }, use) => {
      const problems: string[] = [];
      page.on('pageerror', (error) =>
        problems.push(`pageerror: ${error.message}`),
      );
      page.on('response', (response) => {
        if (response.url().includes('/api/') && response.status() >= 500) {
          problems.push(`HTTP ${response.status()} ${response.url()}`);
        }
      });
      await use(problems);
      expect(problems, problems.join('\n')).toEqual([]);
    },
    { auto: true },
  ],
});

export { expect };

/** 以某个角色的登录态运行（global-setup 生成） */
export const asRole = (user: RoleUser) =>
  test.use({ storageState: storageStatePath(user) });

/** 打开页面并等待数据加载完成 */
export async function visit(page: Page, path: string) {
  await page.goto(path);
  await page.waitForLoadState('networkidle');
}

export const tableRows = (page: Page) =>
  page.locator('.ant-table-tbody tr.ant-table-row');

export const rowWith = (page: Page, text: string | RegExp) =>
  tableRows(page).filter({ hasText: text }).first();

/** 当前打开的弹窗 / 抽屉 */
export const overlay = (page: Page) => page.getByRole('dialog').last();

export async function submitOverlay(page: Page) {
  await overlay(page)
    .getByRole('button', { name: /确\s*定|OK/ })
    .click();
}

/** Popconfirm 确认 */
export async function confirmPopover(page: Page) {
  await page
    .locator('.ant-popconfirm:visible')
    .getByRole('button', { name: /确\s*定|OK/ })
    .click();
}

export async function expectToast(page: Page, text: string | RegExp) {
  await expect(
    page.locator('.ant-message-notice', { hasText: text }).first(),
  ).toBeVisible();
}

/** 在 ProTable 搜索栏输入并查询 */
export async function search(page: Page, fieldId: string, value: string) {
  await page.locator(`.ant-pro-query-filter #${fieldId}`).fill(value);
  await page.getByRole('button', { name: /查\s*询|Query/ }).click();
  await page.waitForLoadState('networkidle');
}

/** 选择 Select 选项（按选项文本） */
export async function selectOption(
  page: Page,
  scope: Locator,
  fieldId: string,
  optionText: string,
) {
  await scope.locator(`.ant-select:has(#${fieldId})`).click();
  await page
    .locator('.ant-select-dropdown:not(.ant-select-dropdown-hidden)')
    .locator('.ant-select-item-option', { hasText: optionText })
    .first()
    .click();
}

/** 登录页表单登录 */
export async function loginByForm(
  page: Page,
  username: string,
  password = 'password',
) {
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.getByRole('button', { name: /登\s*录|Login/ }).click();
}

/** 直接调用 API（带当前页面的 token），用于准备 / 清理数据 */
export async function api<T>(
  page: Page,
  method: string,
  path: string,
  data?: unknown,
): Promise<T> {
  return page.evaluate(
    async ({ method, path, data }) => {
      const response = await fetch(path, {
        method,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          Authorization: `Bearer ${localStorage.getItem('edge_admin_token')}`,
        },
        body: data === undefined ? undefined : JSON.stringify(data),
      });
      if (response.status === 204) return null;
      return response.json();
    },
    { method, path, data },
  ) as Promise<T>;
}
