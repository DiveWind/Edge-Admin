import { join } from 'node:path';
import type { Page } from '@playwright/test';
import {
  api,
  asRole,
  confirmPopover,
  expect,
  expectToast,
  overlay,
  RUN_ID,
  rowWith,
  search,
  selectOption,
  submitOverlay,
  tableRows,
  test,
  visit,
} from './support';

asRole('admin');

/** 通过侧边菜单做站内跳转（不刷新页面，react-query 缓存保留） */
async function navigateViaMenu(page: Page, group: string, item: string) {
  const sider = page.locator('.ant-pro-sider');
  const link = sider.getByRole('link', { name: item, exact: true });
  if (!(await link.isVisible())) {
    await sider.getByText(group, { exact: true }).click();
  }
  await link.click();
  await page.waitForLoadState('networkidle');
}

test.describe('dicts', () => {
  test('dict type and item CRUD with color preview', async ({ page }) => {
    const code = `e2e_${RUN_ID}`;
    await visit(page, '/system/dicts');

    await page.getByRole('button', { name: /新\s*建/ }).click();
    await overlay(page).locator('#name').fill(`E2E 字典 ${RUN_ID}`);
    await overlay(page).locator('#code').fill(code);
    await submitOverlay(page);
    await expectToast(page, '创建成功');

    await search(page, 'code', code);
    await rowWith(page, code).getByText('字典项', { exact: true }).click();
    const drawer = page.getByRole('dialog').filter({ hasText: '字典项：' });
    await expect(drawer).toContainText(code);

    await drawer.getByRole('button', { name: /新建字典项/ }).click();
    const form = overlay(page);
    await form.locator('#label').fill('选项一');
    await form.locator('#value').fill('one');
    await selectOption(page, form, 'color', 'success');
    await expect(
      form.locator('.ant-tag-success', { hasText: '选项一' }),
    ).toBeVisible();
    await submitOverlay(page);
    await expectToast(page, '创建成功');
    await expect(
      drawer.locator('.ant-tag-success', { hasText: '选项一' }),
    ).toBeVisible();

    // 同一字典下 value 重复 → 422 回填
    await drawer.getByRole('button', { name: /新建字典项/ }).click();
    await overlay(page).locator('#label').fill('重复');
    await overlay(page).locator('#value').fill('one');
    await submitOverlay(page);
    await expect(
      overlay(page).locator('.ant-form-item-has-error #value'),
    ).toBeVisible();
    await page.keyboard.press('Escape');

    await drawer
      .locator('.ant-table-row', { hasText: '选项一' })
      .getByText('删除', { exact: true })
      .click();
    await confirmPopover(page);
    await expectToast(page, '删除成功');
    await page.locator('.ant-drawer-close').click();

    await rowWith(page, code).getByText('删除', { exact: true }).click();
    await confirmPopover(page);
    await expectToast(page, '删除成功');
  });

  test('changing a dict refreshes dict-driven selects without reload', async ({
    page,
  }) => {
    const label = `E2E 来源 ${RUN_ID}`;
    // 先在文章页加载（并缓存）字典 article_source
    await visit(page, '/content/articles');
    await expect(tableRows(page).first()).toBeVisible();

    await navigateViaMenu(page, '系统管理', '数据字典');
    await rowWith(page, 'article_source')
      .getByText('字典项', { exact: true })
      .click();
    const drawer = page.getByRole('dialog').filter({ hasText: '字典项：' });
    await drawer.getByRole('button', { name: /新建字典项/ }).click();
    await overlay(page).locator('#label').fill(label);
    await overlay(page).locator('#value').fill(`e2e-${RUN_ID}`);
    await submitOverlay(page);
    await expectToast(page, '创建成功');
    await page.locator('.ant-drawer-close').click();

    await navigateViaMenu(page, '内容管理', '文章管理');
    await page.getByRole('button', { name: /新\s*建/ }).click();
    await overlay(page).locator('.ant-select:has(#source)').click();
    await expect(
      page.locator('.ant-select-item-option', { hasText: label }),
    ).toBeVisible();
    await page.keyboard.press('Escape');

    // 清理
    const types = await api<{ data: { id: number; code: string }[] }>(
      page,
      'GET',
      '/api/dict-types?code=article_source',
    );
    const items = await api<{ data: { id: number; value: string }[] }>(
      page,
      'GET',
      `/api/dict-items?dict_type_id=${types.data[0].id}`,
    );
    const created = items.data.find((item) => item.value === `e2e-${RUN_ID}`);
    if (created) await api(page, 'DELETE', `/api/dict-items/${created.id}`);
  });
});

test.describe('articles', () => {
  test('source column is dict-driven and filterable', async ({ page }) => {
    await visit(page, '/content/articles');
    await expect(tableRows(page).first().locator('.ant-tag')).toBeVisible();
    await page.getByText('展开').click();
    await selectOption(
      page,
      page.locator('.ant-pro-query-filter'),
      'source',
      '转载',
    );
    await page.getByRole('button', { name: /查\s*询/ }).click();
    await page.waitForLoadState('networkidle');
    const rows = tableRows(page);
    const count = await rows.count();
    expect(count).toBeGreaterThan(0);
    for (let index = 0; index < count; index += 1) {
      await expect(rows.nth(index)).toContainText('转载');
    }
  });
});

test.describe('settings', () => {
  test('editing site.name updates the layout title', async ({ page }) => {
    const name = `Edge E2E ${RUN_ID}`;
    await visit(page, '/system/settings');
    const row = rowWith(page, 'site.name');
    await expect(row.getByText('删除', { exact: true })).toHaveCount(0);

    await row.getByText('编辑', { exact: true }).click();
    await expect(overlay(page).locator('#key')).toBeDisabled();
    await overlay(page).locator('#value').fill(name);
    await submitOverlay(page);
    await expectToast(page, '保存成功');
    await expect(page.locator('.ant-pro-global-header-logo')).toContainText(
      name,
    );
    await expect(page).toHaveTitle(new RegExp(name));

    await rowWith(page, 'site.name').getByText('编辑', { exact: true }).click();
    await overlay(page).locator('#value').fill('Edge Admin');
    await submitOverlay(page);
    await expectToast(page, '保存成功');
    await expect(page.locator('.ant-pro-global-header-logo')).toContainText(
      'Edge Admin',
    );
  });

  test('custom json setting: validation, create, delete', async ({ page }) => {
    const key = `e2e.json_${RUN_ID}`;
    await visit(page, '/system/settings');
    await page.getByRole('button', { name: /新\s*建/ }).click();
    const form = overlay(page);
    await form.locator('#group').fill('e2e');
    await form.locator('#name').fill('E2E JSON');
    await form.locator('#key').fill(key);
    await selectOption(page, form, 'type', 'JSON');
    await form.locator('#value').fill('{bad');
    await submitOverlay(page);
    await expect(form.getByText('请输入合法的 JSON')).toBeVisible();
    await form.locator('#value').fill('{"a": [1, 2]}');
    await submitOverlay(page);
    await expectToast(page, '创建成功');

    await expect(rowWith(page, key)).toContainText('{"a":[1,2]}');
    await rowWith(page, key).getByText('删除', { exact: true }).click();
    await confirmPopover(page);
    await expectToast(page, '删除成功');
  });
});

test.describe('attachments', () => {
  test('upload, preview, copy link and delete', async ({ page }) => {
    await visit(page, '/system/attachments');
    await page
      .locator('.ant-pro-table-list-toolbar input[type=file]')
      .setInputFiles(join(__dirname, 'fixtures', 'image.png'));
    await expectToast(page, '上传成功');
    const row = rowWith(page, 'image.png');
    await expect(row.locator('img')).toBeVisible();
    await expect(row).toContainText('image/png');
    await expect(row).toContainText(/\d+ B/);

    await row.getByText('复制链接').click();
    await expect(row.getByText('链接已复制')).toBeVisible();

    await row.getByText('删除', { exact: true }).click();
    await confirmPopover(page);
    await expectToast(page, '删除成功');
    await expect(rowWith(page, 'image.png')).toHaveCount(0);
  });
});

test.describe('online sessions', () => {
  test('current session is marked; another session can be forced out', async ({
    page,
  }) => {
    // 为 viewer 新建一个会话
    const response = await page.request.post('/api/auth/login', {
      headers: { Accept: 'application/json' },
      data: { username: 'viewer', password: 'password' },
    });
    expect(response.ok()).toBeTruthy();
    const { data } = (await response.json()) as { data: { token: string } };

    await visit(page, '/monitor/online-sessions');
    const current = rowWith(page, '当前会话');
    await expect(current).toBeVisible();
    await expect(
      current.locator('.ant-typography-disabled', { hasText: '强制下线' }),
    ).toBeVisible();

    await search(page, 'username', 'viewer');
    // viewer 可能有多个会话（global-setup 也登录过），下线最新的一个（列表按 id 倒序）
    await tableRows(page).first().getByText('强制下线').click();
    await confirmPopover(page);
    await expectToast(page, '已强制下线');

    const status = await page.evaluate(async (token) => {
      const result = await fetch('/api/auth/me', {
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
        },
      });
      return result.status;
    }, data.token);
    expect(status).toBe(401);
  });
});

test.describe('theme and language', () => {
  test('dark theme toggles and persists', async ({ page }) => {
    await visit(page, '/dashboard');
    const background = () =>
      page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    const light = await background();
    await page.getByRole('button', { name: '切换到暗色主题' }).click();
    await expect.poll(background).not.toBe(light);
    await page.reload();
    await expect(
      page.getByRole('button', { name: '切换到亮色主题' }),
    ).toBeVisible();
    await page.getByRole('button', { name: '切换到亮色主题' }).click();
    await expect.poll(background).toBe(light);
  });

  test('en-US: backend messages and permission names follow the UI language', async ({
    page,
  }) => {
    await visit(page, '/system/positions');
    await page.locator('[aria-label="切换语言"]').hover();
    await page.getByText('English').click();
    await expect(page.getByRole('button', { name: /New/ })).toBeVisible();

    // Accept-Language 跟随界面语言：422 文案为英文
    await page.getByRole('button', { name: /New/ }).click();
    await overlay(page).locator('#name').fill(`E2E ${RUN_ID}`);
    await overlay(page).locator('#code').fill('ceo');
    await submitOverlay(page);
    await expect(
      overlay(page).locator('.ant-form-item-explain-error').first(),
    ).toContainText(/taken/i);
    await page.keyboard.press('Escape');

    // 角色授权树按 code 翻译
    await visit(page, '/system/roles');
    await rowWith(page, 'editor').getByText('Edit', { exact: true }).click();
    const tree = overlay(page).locator('.ant-tree').first();
    await expect(tree).toContainText('System');
    await expect(tree).toContainText('Users');
    await expect(tree).not.toContainText('系统管理');
    await page.keyboard.press('Escape');

    await page.evaluate(() => localStorage.setItem('umi_locale', 'zh-CN'));
  });
});
