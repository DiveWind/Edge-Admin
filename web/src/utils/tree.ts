import type { TreeDataNode } from 'antd';

/** 树形数据小工具，适用于契约里 `{id, children}` 结构的树（部门、权限） */
export interface IdTreeNode {
  id: API.Id;
  children?: IdTreeNode[];
}

/** 所有有子节点的节点 id，用于树形表格默认全部展开 */
export function collectParentIds<T extends IdTreeNode>(nodes: T[]): API.Id[] {
  return nodes.flatMap((node) =>
    node.children?.length ? [node.id, ...collectParentIds(node.children)] : [],
  );
}

/** 所有叶子节点 id */
export function collectLeafIds<T extends IdTreeNode>(nodes: T[]): API.Id[] {
  return nodes.flatMap((node) =>
    node.children?.length ? collectLeafIds(node.children) : [node.id],
  );
}

interface NamedTreeNode {
  id: API.Id;
  name: string;
  children?: NamedTreeNode[];
}

/** `{id, name, children}` → antd Tree 的 `{key, title, children}` */
export function toTreeData(nodes: NamedTreeNode[]): TreeDataNode[] {
  return nodes.map((node) => ({
    key: node.id,
    title: node.name,
    children: node.children?.length ? toTreeData(node.children) : undefined,
  }));
}

export type Disableable<T extends IdTreeNode> = Omit<T, 'children'> & {
  disabled: boolean;
  children?: Disableable<T>[];
};

/** 返回新树：`id` 节点及其所有后代标记为 disabled（选择上级时不能选自己或自己的下级） */
export function disableSubtree<T extends IdTreeNode & { children?: T[] }>(
  nodes: T[],
  id: API.Id,
  disabled = false,
): Disableable<T>[] {
  return nodes.map((node) => {
    const nodeDisabled = disabled || node.id === id;
    return {
      ...node,
      disabled: nodeDisabled,
      children: node.children
        ? disableSubtree(node.children, id, nodeDisabled)
        : undefined,
    };
  });
}
