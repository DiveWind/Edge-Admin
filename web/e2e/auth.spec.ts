import { expect, FORBIDDEN_TEXT, loginByForm, test, visit } from './support';

/** 登录 / 登出（不复用登录态） */
test.describe('auth', () => {
  test('login page shows public settings and returns to redirect target', async ({
    page,
  }) => {
    await page.goto('/system/users');
    await expect(page).toHaveURL(/\/user\/login\?redirect=/);
    // 公开系统参数：站点名称 + 登录页公告（seed 中有值）
    await expect(page.getByText('Edge Admin').first()).toBeVisible();
    await expect(page.locator('.ant-alert-warning')).toContainText('演示环境');
    await expect(page).toHaveTitle(/Edge Admin/);

    await loginByForm(page, 'admin');
    await expect(page).toHaveURL(/\/system\/users$/);
    await expect(page.getByText(FORBIDDEN_TEXT)).toHaveCount(0);
    await expect(page).toHaveTitle(/用户管理 - Edge Admin/);
  });

  test('wrong password and disabled account show field errors', async ({
    page,
  }) => {
    await visit(page, '/user/login');
    const fieldError = page.locator('.ant-form-item-explain-error').first();

    await loginByForm(page, 'editor', 'wrong-password');
    await expect(fieldError).toBeVisible();
    await expect(page).toHaveURL(/\/user\/login/);

    await loginByForm(page, 'disabled');
    await expect(fieldError).toBeVisible();
    await expect(page).toHaveURL(/\/user\/login/);
  });

  test('logout revokes the token', async ({ page }) => {
    await visit(page, '/user/login');
    await loginByForm(page, 'admin');
    await expect(page).not.toHaveURL(/\/user\/login/);
    const token = await page.evaluate(() =>
      localStorage.getItem('edge_admin_token'),
    );

    await page.locator('.ant-pro-global-header-header-actions-avatar').hover();
    await page.getByRole('menuitem', { name: '退出登录' }).click();
    await expect(page).toHaveURL(/\/user\/login/);

    // 旧 token 已在服务端吊销
    const status = await page.evaluate(async (value) => {
      const response = await fetch('/api/auth/me', {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${value}`,
        },
      });
      return response.status;
    }, token);
    expect(status).toBe(401);

    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/user\/login\?redirect=/);
  });
});
