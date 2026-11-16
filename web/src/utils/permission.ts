/**
 * 权限点名称 i18n：后端约定菜单 / 权限点名称由前端按 code 翻译，后端 `name` 作为回退。
 * 查找顺序：`permission.<code>` → 操作类 `permission.action.<最后一段>` → 后端 name。
 */
export type MessageLookup = (id: string) => string | undefined;

interface NamedPermission {
  code: string;
  name: string;
  type: 'menu' | 'action';
}

export function permissionName(
  node: NamedPermission,
  lookup: MessageLookup,
): string {
  const exact = lookup(`permission.${node.code}`);
  if (exact) return exact;
  if (node.type === 'action') {
    const action = node.code.slice(node.code.lastIndexOf('.') + 1);
    const generic = lookup(`permission.action.${action}`);
    if (generic) return generic;
  }
  return node.name;
}

/** 返回新树：每个节点的 name 换成当前语言的名称 */
export function translatePermissionTree<
  T extends NamedPermission & { children?: T[] },
>(nodes: T[], lookup: MessageLookup): T[] {
  return nodes.map((node) => ({
    ...node,
    name: permissionName(node, lookup),
    children: node.children
      ? translatePermissionTree(node.children, lookup)
      : node.children,
  }));
}
