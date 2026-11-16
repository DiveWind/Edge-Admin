import { describe, expect, it } from 'vitest';
import type { MessageLookup } from './permission';
import { permissionName, translatePermissionTree } from './permission';

const zh: Record<string, string> = {
  'permission.system': '系统管理',
  'permission.system.user': '用户管理',
  'permission.action.view': '查看',
  'permission.action.delete': '删除',
  'permission.monitor.online-session.delete': '强制下线',
};
const en: Record<string, string> = {
  'permission.system': 'System',
  'permission.system.user': 'Users',
  'permission.action.view': 'View',
  'permission.action.delete': 'Delete',
  'permission.monitor.online-session.delete': 'Force logout',
};
const lookup: MessageLookup = (id) => en[id];
const defaults: MessageLookup = (id) => zh[id];

describe('permissionName', () => {
  it('uses the exact code translation first', () => {
    expect(
      permissionName(
        { code: 'system.user', name: '用户管理', type: 'menu' },
        lookup,
        defaults,
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
        defaults,
      ),
    ).toBe('Force logout');
  });

  it('falls back to the generic action translation for actions', () => {
    expect(
      permissionName(
        { code: 'content.tag.delete', name: '删除', type: 'action' },
        lookup,
        defaults,
      ),
    ).toBe('Delete');
  });

  it('shows the backend name when the node was renamed by an administrator', () => {
    expect(
      permissionName(
        { code: 'system.user', name: '成员管理', type: 'menu' },
        lookup,
        defaults,
      ),
    ).toBe('成员管理');
    expect(
      permissionName(
        { code: 'content.tag.delete', name: '移除', type: 'action' },
        lookup,
        defaults,
      ),
    ).toBe('移除');
  });

  it('falls back to the backend name when nothing matches', () => {
    expect(
      permissionName(
        { code: 'content.tag', name: '标签', type: 'menu' },
        lookup,
        defaults,
      ),
    ).toBe('标签');
    expect(
      permissionName(
        { code: 'content.tag.export', name: '导出', type: 'action' },
        lookup,
        defaults,
      ),
    ).toBe('导出');
    // 菜单 / 目录节点不走通用操作文案（避免 `xxx.view` 形式的菜单码被误翻）
    expect(
      permissionName(
        { code: 'demo.view', name: '查看', type: 'menu' },
        lookup,
        defaults,
      ),
    ).toBe('查看');
  });
});

describe('translatePermissionTree', () => {
  it('translates names recursively without mutating the input', () => {
    interface Node {
      id: number;
      name: string;
      code: string;
      type: API.PermissionType;
      children?: Node[];
    }
    const tree: Node[] = [
      {
        id: 1,
        name: '系统管理',
        code: 'system',
        type: 'directory',
        children: [
          {
            id: 2,
            name: '查看',
            code: 'system.user.view',
            type: 'action',
            children: [],
          },
        ],
      },
    ];
    const translated = translatePermissionTree(tree, lookup, defaults);
    expect(translated[0].name).toBe('System');
    expect(translated[0].children?.[0].name).toBe('View');
    expect(translated[0].children?.[0].children).toEqual([]);
    expect(tree[0].name).toBe('系统管理');
  });
});
