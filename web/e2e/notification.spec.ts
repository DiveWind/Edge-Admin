import {
  api,
  asRole,
  confirmPopover,
  expect,
  expectToast,
  overlay,
  RUN_ID,
  rowWith,
  submitOverlay,
  test,
  visit,
} from './support';

asRole('admin');

/** 公告发布 → 队列发送站内通知 → 铃铛 → 通知中心标记已读（需要 queue:work） */
test('publishing an announcement notifies users', async ({ page }) => {
  test.setTimeout(120_000);
  const title = `E2E 公告 ${RUN_ID}`;
  await visit(page, '/message/announcements');
  const unread = async () =>
    (
      await api<{ data: { count: number } }>(
        page,
        'GET',
        '/api/notifications/unread-count',
      )
    ).data.count;
  const before = await unread();

  await page.getByRole('button', { name: /新\s*建/ }).click();
  await overlay(page).locator('#title').fill(title);
  await overlay(page).locator('#content').fill('e2e content');
  await submitOverlay(page);
  await expectToast(page, '创建成功');

  await rowWith(page, title).getByText('发布', { exact: true }).click();
  await confirmPopover(page);
  await expectToast(page, '已发布');

  // 通知由队列异步发送
  await expect
    .poll(unread, { timeout: 60_000, intervals: [2_000] })
    .toBeGreaterThan(before);

  await visit(page, '/dashboard');
  await page.locator('[aria-label="通知"]').click();
  const popover = page.locator('.ant-popover:visible');
  await expect(popover).toContainText(title);
  await popover.getByRole('button', { name: '查看全部' }).click();

  await expect(page).toHaveURL(/\/message\/notifications/);
  await rowWith(page, title).getByText('标记已读').click();
  await expect(rowWith(page, title).getByText('标记已读')).toHaveCount(0);
});
