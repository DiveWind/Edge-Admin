import { describe, expect, it } from 'vitest';
import {
  allowedChildTypes,
  defaultChildType,
  findNode,
  parentOptions,
} from './tree';

interface Node {
  id: number;
  name: string;
  type: API.PermissionType;
  children?: Node[];
}

const tree: Node[] = [
  {
    id: 1,
    name: 'system',
    type: 'directory',
    children: [
      {
        id: 2,
        name: 'users',
        type: 'menu',
        children: [{ id: 3, name: 'view', type: 'action' }],
      },
      { id: 4, name: 'sub', type: 'directory' },
    ],
  },
];

describe('menu tree rules', () => {
  it('knows which types each parent accepts', () => {
    expect(allowedChildTypes(null)).toEqual(['directory', 'menu']);
    expect(allowedChildTypes('menu')).toEqual(['action']);
    expect(allowedChildTypes('action')).toEqual([]);
    expect(defaultChildType(null)).toBe('directory');
    expect(defaultChildType('directory')).toBe('menu');
    expect(defaultChildType('menu')).toBe('action');
  });

  it('disables parents that cannot hold the type, and the node itself with its descendants', () => {
    const forMenu = parentOptions(tree, 'menu');
    expect(forMenu[0].disabled).toBe(false);
    expect(forMenu[0].children?.map((node) => node.disabled)).toEqual([
      true,
      false,
    ]);
    // 按钮不出现在上级选项中
    expect(forMenu[0].children?.[0].children).toBeUndefined();

    const forAction = parentOptions(tree, 'action');
    expect(forAction[0].disabled).toBe(true);
    expect(forAction[0].children?.[0].disabled).toBe(false);

    const editingSystem = parentOptions(tree, 'directory', 1);
    expect(editingSystem[0].disabled).toBe(true);
    expect(editingSystem[0].children?.[1].disabled).toBe(true);
  });

  it('finds nested nodes', () => {
    expect(findNode(tree, 3)?.name).toBe('view');
    expect(findNode(tree, 9)).toBeUndefined();
  });
});
