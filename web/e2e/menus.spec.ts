import { readFileSync } from 'node:fs';
import type { APIRequestContext, Page } from '@playwright/test';
import {
  asRole,
  confirmPopover,
  expect,
  expectToast,
  FORBIDDEN_TEXT,
  overlay,
  RUN_ID,
  rowWith,
  search,
  storageStatePath,
  submitOverlay,
  test,
  visit,
} from './support';

/** 动态菜单：菜单管理 CRUD、侧边栏即时变化、隐藏 / 停用、外链、按角色收敛 */

const sider = (page: Page) => page.locator('.ant-pro-sider');

/** global-setup 生成的 admin token，用于在用例前后通过 API 准备 / 还原数据 */
function adminHeaders() {
  const state = JSON.parse(readFileSync(storageStatePath('admin'), 'utf8')) as {
    origins: { localStorage: { name: string; value: string }[] }[];
  };
  const token = state.origins[0].localStorage.find(
    (item) => item.name === 'edge_admin_token',
  )?.value;
  return { Accept: 'application/json', Authorization: `Bearer ${token}` };
}

interface Node {
  id: number;
  parent_id: number | null;
  name: string;
  code: string;
  type: 'directory' | 'menu' | 'action';
  path: string | null;
  icon: string | null;
  is_external: boolean;
  is_visible: boolean;
  status: 'active' | 'disabled';
  sort: number;
  remark: string | null;
  children: Node[];
}

async function findNode(request: APIRequestContext, code: string) {
  const response = await request.get(
    `/api/permissions?code=${encodeURIComponent(code)}`,
    { headers: adminHeaders() },
  );
  const { data } = (await response.json()) as { data: Node[] };
  const walk = (nodes: Node[]): Node | undefined =>
    nodes.reduce<Node | undefined>(
      (found, node) =>
        found ?? (node.code === code ? node : walk(node.children)),
      undefined,
    );
  const node = walk(data);
  if (!node) throw new Error(`menu ${code} not found`);
  return node;
}

/** 把节点恢复为给定字段（内置节点用例结束后还原） */
async function restore(request: APIRequestContext, node: Node) {
  const { children: _children, ...fields } = node;
  const response = await request.put(`/api/permissions/${node.id}`, {
    headers: adminHeaders(),
    data: fields,
  });
  expect(response.ok()).toBeTruthy();
}

async function pickIcon(page: Page, name: string) {
  await overlay(page).locator('#icon').click();
  await page
    .getByPlaceholder('搜索图标名称')
    .fill(name.replace('Outlined', ''));
  await page.getByRole('button', { name, exact: true }).click();
}

test.describe('admin', () => {
  asRole('admin');

  test('menu management CRUD with an external link shows up in the sidebar immediately', async ({
    page,
    request,
  }) => {
    try {
      await crud(page);
    } finally {
      // 失败时清理残留（先删外链再删目录；已删除时 404 忽略）
      for (const code of [`e2e-${RUN_ID}.link`, `e2e-${RUN_ID}`]) {
        const node = await findNode(request, code).catch(() => undefined);
        if (node) {
          await request.delete(`/api/permissions/${node.id}`, {
            headers: adminHeaders(),
          });
        }
      }
    }
  });
});

async function crud(page: Page) {
  const directory = `E2E 目录 ${RUN_ID}`;
  const link = `E2E 外链 ${RUN_ID}`;
  await page
    .context()
    .route('https://example.com/**', (route) =>
      route.fulfill({ body: 'external' }),
    );
  await visit(page, '/system/permissions');

  // 新建顶级目录（选图标）
  await page.getByRole('button', { name: /新\s*建/ }).click();
  const form = overlay(page);
  await form.getByText('目录', { exact: true }).click();
  await form.locator('#name').fill(directory);
  await form.locator('#code').fill(`e2e-${RUN_ID}`);
  await pickIcon(page, 'RocketOutlined');
  await expect(form.locator('#icon')).toHaveValue('RocketOutlined');
  await submitOverlay(page);
  await expectToast(page, '创建成功');
  // 没有子菜单的目录不出现在侧边栏
  await expect(sider(page)).not.toContainText(directory);

  // 添加下级：预填上级与类型（菜单），外链地址必须是 URL（422 回填）
  await search(page, 'name', directory);
  await rowWith(page, directory).getByText('添加下级').click();
  await expect(
    overlay(page).getByRole('radio', { name: '菜单' }),
  ).toBeChecked();
  await overlay(page).locator('#name').fill(link);
  await overlay(page).locator('#code').fill(`e2e-${RUN_ID}.link`);
  await overlay(page).locator('#is_external').click();
  await overlay(page).locator('#path').fill('/not-a-url');
  await submitOverlay(page);
  await expect(
    overlay(page).locator('.ant-form-item-has-error #path'),
  ).toBeVisible();
  await overlay(page).locator('#path').fill('https://example.com/docs');
  await submitOverlay(page);
  await expectToast(page, '创建成功');

  // 侧边栏：目录（有了子菜单）与其下的外链菜单即时出现，外链新窗口打开
  await expect(sider(page)).toContainText(directory);
  await sider(page).getByText(directory).click();
  const [popup] = await Promise.all([
    page.waitForEvent('popup'),
    sider(page).getByText(link).click(),
  ]);
  await expect(popup).toHaveURL('https://example.com/docs');
  await popup.close();

  // 有下级的目录不能删除（403 提示）
  await visit(page, '/system/permissions');
  await search(page, 'name', 'E2E');
  await rowWith(page, directory).getByText('删除', { exact: true }).click();
  await confirmPopover(page);
  await expectToast(page, '存在下级节点');

  // 改名后侧边栏即时变化
  await rowWith(page, directory).getByText('编辑', { exact: true }).click();
  await overlay(page).locator('#name').fill(`${directory} 改`);
  await submitOverlay(page);
  await expectToast(page, '保存成功');
  await expect(sider(page)).toContainText(`${directory} 改`);

  // 先删下级再删目录，侧边栏随之消失
  await rowWith(page, link).getByText('删除', { exact: true }).click();
  await confirmPopover(page);
  await expectToast(page, '删除成功');
  await rowWith(page, `${directory} 改`)
    .getByText('删除', { exact: true })
    .click();
  await confirmPopover(page);
  await expectToast(page, '删除成功');
  await expect(sider(page)).not.toContainText(directory);
}

test.describe('admin menus', () => {
  asRole('admin');

  test('renaming, changing the icon and the order of a builtin menu updates the sidebar', async ({
    page,
    request,
  }) => {
    const original = await findNode(request, 'content');
    try {
      await visit(page, '/system/permissions');
      await search(page, 'name', '内容管理');
      await rowWith(page, '内容管理')
        .getByText('编辑', { exact: true })
        .click();
      // 内置节点：权限码只读
      await expect(overlay(page).locator('#code')).toBeDisabled();
      await overlay(page).locator('#name').fill('内容中心');
      await pickIcon(page, 'RocketOutlined');
      await overlay(page).locator('#sort').fill('0');
      await submitOverlay(page);
      await expectToast(page, '保存成功');

      const menu = sider(page);
      await expect(menu).toContainText('内容中心');
      await expect(menu).not.toContainText('内容管理');
      await expect(
        menu
          .locator('.ant-menu-submenu', { hasText: '内容中心' })
          .locator('.anticon-rocket'),
      ).toBeVisible();
      // sort 0 与工作台并列，按 id 排在工作台之后、系统管理之前
      const titles = await menu
        .locator(':scope .ant-menu-root > li')
        .allInnerTexts();
      expect(
        titles.findIndex((text) => text.includes('内容中心')),
      ).toBeLessThan(titles.findIndex((text) => text.includes('系统管理')));
    } finally {
      await restore(request, original);
    }
  });

  test('hidden menus stay accessible while disabled menus lose their permissions', async ({
    page,
    request,
  }) => {
    const original = await findNode(request, 'content.category');
    try {
      await visit(page, '/system/permissions');
      await search(page, 'name', '文章分类');
      const row = rowWith(page, '文章分类');

      await row.getByRole('switch', { name: '显示' }).click();
      await expect(sider(page)).not.toContainText('文章分类');
      await visit(page, '/content/categories');
      await expect(page.getByText(FORBIDDEN_TEXT)).toHaveCount(0);
      await expect(page.locator('.ant-table-row').first()).toBeVisible();

      await visit(page, '/system/permissions');
      await search(page, 'name', '文章分类');
      await rowWith(page, '文章分类')
        .getByRole('switch', { name: '状态' })
        .click();
      await expect(
        rowWith(page, '文章分类').getByRole('switch', { name: '状态' }),
      ).not.toBeChecked();
      await visit(page, '/content/categories');
      await expect(page.getByText(FORBIDDEN_TEXT)).toBeVisible();
    } finally {
      await restore(request, original);
    }
  });
});

test.describe('editor', () => {
  asRole('editor');

  test('does not see menus it is not granted', async ({ page, request }) => {
    const created = await request.post('/api/permissions', {
      headers: adminHeaders(),
      data: {
        type: 'menu',
        parent_id: null,
        name: `E2E 未授权 ${RUN_ID}`,
        code: `e2e-hidden-${RUN_ID}`,
        path: 'https://example.com',
        is_external: true,
        is_visible: true,
        status: 'active',
        sort: 0,
      },
    });
    const { data } = (await created.json()) as { data: Node };
    try {
      await visit(page, '/');
      const menu = sider(page);
      await expect(menu).toContainText('内容管理');
      await expect(menu).not.toContainText(`E2E 未授权 ${RUN_ID}`);
      await expect(menu).not.toContainText('系统监控');
      await expect(menu).not.toContainText('菜单管理');
      await expect(menu).not.toContainText('用户管理');
    } finally {
      await request.delete(`/api/permissions/${data.id}`, {
        headers: adminHeaders(),
      });
    }
  });
});
