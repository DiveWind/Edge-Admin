import {
  asRole,
  expect,
  FORBIDDEN_TEXT,
  tableRows,
  test,
  visit,
} from './support';

asRole('admin');

/** 每个模块的冒烟：能打开、无 403、有数据（列表页至少一行） */
const PAGES: { path: string; rows: boolean }[] = [
  { path: '/system/users', rows: true },
  { path: '/system/roles', rows: true },
  { path: '/system/permissions', rows: true },
  { path: '/system/departments', rows: true },
  { path: '/system/positions', rows: true },
  { path: '/system/dicts', rows: true },
  { path: '/system/settings', rows: true },
  { path: '/system/attachments', rows: false },
  { path: '/monitor/login-logs', rows: true },
  { path: '/monitor/operation-logs', rows: true },
  { path: '/monitor/tasks', rows: true },
  { path: '/monitor/online-sessions', rows: true },
  { path: '/monitor/system-info', rows: false },
  { path: '/message/announcements', rows: true },
  { path: '/message/notifications', rows: false },
  { path: '/content/articles', rows: true },
  { path: '/content/categories', rows: true },
  { path: '/account/settings', rows: false },
];

for (const { path, rows } of PAGES) {
  test(`smoke ${path}`, async ({ page }) => {
    await visit(page, path);
    await expect(page.getByText(FORBIDDEN_TEXT)).toHaveCount(0);
    await expect(page.locator('.ant-pro-page-container')).toBeVisible();
    if (rows) await expect(tableRows(page).first()).toBeVisible();
  });
}

test('dashboard renders 8 stat cards and charts', async ({ page }) => {
  await visit(page, '/dashboard');
  await expect(page.locator('.ant-pro-statistic-card')).toHaveCount(8);
  await expect(page.locator('.ant-pro-card canvas').first()).toBeVisible();
  await expect(page.getByText('在线会话')).toBeVisible();
  await expect(page.getByText('附件数')).toBeVisible();
});

test('system info shows grouped descriptions', async ({ page }) => {
  await visit(page, '/monitor/system-info');
  for (const title of ['应用', '运行环境', '驱动', '存储']) {
    await expect(
      page.locator('.ant-pro-card-title', { hasText: title }),
    ).toBeVisible();
  }
  await expect(page.getByText('PHP 版本')).toBeVisible();
});
