/**
 * 菜单树层级规则（与后端一致，契约 §3.1）：目录下可放目录 / 菜单，菜单下只能放按钮，按钮没有下级。
 */
type ParentType = API.PermissionType | null;

const CHILD_TYPES: Record<API.PermissionType | 'root', API.PermissionType[]> = {
  root: ['directory', 'menu'],
  directory: ['directory', 'menu'],
  menu: ['action'],
  action: [],
};

/** 某个上级（null 为顶级）下允许的类型 */
export function allowedChildTypes(parent: ParentType): API.PermissionType[] {
  return CHILD_TYPES[parent ?? 'root'];
}

/** 预选的类型：顶级默认目录，目录下默认菜单，菜单下只能是按钮 */
const DEFAULT_CHILD_TYPE: Record<
  API.PermissionType | 'root',
  API.PermissionType
> = {
  root: 'directory',
  directory: 'menu',
  menu: 'action',
  action: 'action',
};

/** 在某个上级下"添加下级"时预选的类型 */
export function defaultChildType(parent: ParentType): API.PermissionType {
  return DEFAULT_CHILD_TYPE[parent ?? 'root'];
}

export interface ParentOption {
  id: API.Id;
  name: string;
  type: API.PermissionType;
  disabled: boolean;
  children?: ParentOption[];
}

/**
 * 上级选择树：按钮不能作为上级（不出现）；不能容纳 `type` 的节点、自己及自己的下级禁用。
 */
export function parentOptions<
  T extends {
    id: API.Id;
    name: string;
    type: API.PermissionType;
    children?: T[];
  },
>(
  nodes: T[],
  type: API.PermissionType,
  selfId?: API.Id,
  inSelf = false,
): ParentOption[] {
  return nodes
    .filter((node) => node.type !== 'action')
    .map((node) => {
      const self = inSelf || node.id === selfId;
      const children = parentOptions(node.children ?? [], type, selfId, self);
      return {
        id: node.id,
        name: node.name,
        type: node.type,
        disabled: self || !allowedChildTypes(node.type).includes(type),
        children: children.length ? children : undefined,
      };
    });
}

/** 平铺查找节点 */
export function findNode<T extends { id: API.Id; children?: T[] }>(
  nodes: T[],
  id: API.Id | null | undefined,
): T | undefined {
  for (const node of nodes) {
    if (node.id === id) return node;
    const found = findNode(node.children ?? [], id);
    if (found) return found;
  }
  return undefined;
}
