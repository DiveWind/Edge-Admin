import {
  asRole,
  confirmPopover,
  expect,
  expectToast,
  overlay,
  RUN_ID,
  rowWith,
  search,
  submitOverlay,
  test,
  visit,
} from './support';

asRole('admin');

/** 一个完整的 CRUD：岗位（含 422 回填） */
test('positions: create (422 then success), edit, delete', async ({ page }) => {
  const name = `E2E 岗位 ${RUN_ID}`;
  await visit(page, '/system/positions');

  await page.getByRole('button', { name: /新\s*建/ }).click();
  const form = overlay(page);
  await form.locator('#name').fill(name);
  // 已存在的编码 → 422，错误回填到字段，弹窗不关闭
  await form.locator('#code').fill('ceo');
  await submitOverlay(page);
  await expect(form.locator('.ant-form-item-has-error #code')).toBeVisible();

  await form.locator('#code').fill(`e2e-${RUN_ID}`);
  await submitOverlay(page);
  await expectToast(page, '创建成功');
  await expect(page.getByRole('dialog')).toHaveCount(0);

  await search(page, 'name', name);
  await expect(rowWith(page, name)).toBeVisible();

  await rowWith(page, name).getByText('编辑', { exact: true }).click();
  await expect(overlay(page).locator('#name')).toHaveValue(name);
  await overlay(page).locator('#sort').fill('9');
  await submitOverlay(page);
  await expectToast(page, '保存成功');
  await expect(rowWith(page, name)).toContainText('9');

  await rowWith(page, name).getByText('删除', { exact: true }).click();
  await confirmPopover(page);
  await expectToast(page, '删除成功');
  await expect(rowWith(page, name)).toHaveCount(0);
});
