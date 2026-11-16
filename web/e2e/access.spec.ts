import { asRole, expect, FORBIDDEN_TEXT, test, visit } from './support';

const sider = (page: import('@playwright/test').Page) =>
  page.locator('.ant-pro-sider');

/** 各角色菜单收敛、按钮级权限与 403 */
test.describe('editor', () => {
  asRole('editor');

  test('sees content menus only and gets 403 on system users', async ({
    page,
  }) => {
    await visit(page, '/');
    await expect(page).not.toHaveURL(/\/$/);
    await expect(page.getByText(FORBIDDEN_TEXT)).toHaveCount(0);

    const menu = sider(page);
    await expect(menu).toContainText('内容管理');
    await expect(menu).not.toContainText('系统监控');

    await visit(page, '/content/articles');
    await expect(page.getByRole('button', { name: /新\s*建/ })).toBeVisible();

    await visit(page, '/system/users');
    await expect(page.getByText(FORBIDDEN_TEXT)).toBeVisible();
    await visit(page, '/monitor/online-sessions');
    await expect(page.getByText(FORBIDDEN_TEXT)).toBeVisible();
  });
});

test.describe('viewer', () => {
  asRole('viewer');

  test('can view every module but has no write buttons', async ({ page }) => {
    for (const path of [
      '/content/articles',
      '/system/users',
      '/system/dicts',
      '/system/settings',
    ]) {
      await visit(page, path);
      await expect(page.getByText(FORBIDDEN_TEXT)).toHaveCount(0);
      await expect(page.getByRole('button', { name: /新\s*建/ })).toHaveCount(
        0,
      );
      await expect(
        page.locator('.ant-table-tbody a', { hasText: /^编辑$/ }),
      ).toHaveCount(0);
    }
    // 在线会话可查看但不能强制下线
    await visit(page, '/monitor/online-sessions');
    await expect(page.getByText('强制下线')).toHaveCount(0);
  });
});

test.describe('marketing', () => {
  asRole('marketing');

  test('lands on the first permitted page and menus are pruned', async ({
    page,
  }) => {
    await visit(page, '/');
    await expect(page.getByText(FORBIDDEN_TEXT)).toHaveCount(0);
    const menu = sider(page);
    await expect(menu).toContainText('工作台');
    await expect(menu).toContainText('内容管理');
    await expect(menu).not.toContainText('系统管理');

    await visit(page, '/content/articles');
    await expect(page.getByRole('button', { name: /新\s*建/ })).toHaveCount(0);
    await visit(page, '/content/categories');
    await expect(page.getByText(FORBIDDEN_TEXT)).toBeVisible();
  });
});
