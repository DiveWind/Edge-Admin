import { describe, expect, it } from 'vitest';
import type { MessageLookup } from './permission';
import { permissionName, translatePermissionTree } from './permission';

const messages: Record<string, string> = {
  'permission.system': 'System',
  'permission.system.user': 'Users',
  'permission.action.view': 'View',
  'permission.action.delete': 'Delete',
  'permission.monitor.online-session.delete': 'Force logout',
};
const lookup: MessageLookup = (id) => messages[id];

describe('permissionName', () => {
  it('uses the exact code translation first', () => {
    expect(
      permissionName(
        { code: 'system.user', name: '用户管理', type: 'menu' },
        lookup,
      ),
    ).toBe('Users');
    expect(
      permissionName(
        {
          code: 'monitor.online-session.delete',
          name: '强制下线',
          type: 'action',
        },
        lookup,
      ),
    ).toBe('Force logout');
  });

  it('falls back to the generic action translation for actions', () => {
    expect(
      permissionName(
        { code: 'content.tag.delete', name: '删除', type: 'action' },
        lookup,
      ),
    ).toBe('Delete');
  });

  it('falls back to the backend name when nothing matches', () => {
    expect(
      permissionName(
        { code: 'content.tag', name: '标签', type: 'menu' },
        lookup,
      ),
    ).toBe('标签');
    expect(
      permissionName(
        { code: 'content.tag.export', name: '导出', type: 'action' },
        lookup,
      ),
    ).toBe('导出');
    // 菜单节点不走通用操作文案（避免 `xxx.view` 形式的菜单码被误翻）
    expect(
      permissionName({ code: 'demo.view', name: '演示', type: 'menu' }, lookup),
    ).toBe('演示');
  });
});

describe('translatePermissionTree', () => {
  it('translates names recursively without mutating the input', () => {
    const tree: API.PermissionNode[] = [
      {
        id: 1,
        parent_id: null,
        name: '系统管理',
        code: 'system',
        type: 'menu',
        sort: 0,
        children: [
          {
            id: 2,
            parent_id: 1,
            name: '查看',
            code: 'system.user.view',
            type: 'action',
            sort: 0,
            children: [],
          },
        ],
      },
    ];
    const translated = translatePermissionTree(tree, lookup);
    expect(translated[0].name).toBe('System');
    expect(translated[0].children?.[0].name).toBe('View');
    expect(translated[0].children?.[0].children).toEqual([]);
    expect(tree[0].name).toBe('系统管理');
  });
});
