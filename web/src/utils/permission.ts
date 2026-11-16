/**
 * 菜单 / 权限点名称 i18n：后端 `name` 是管理员可编辑的数据（中文），前端按 code 翻译。
 *
 * 查找顺序：`permission.<code>` → 操作类 `permission.action.<最后一段>` → 后端 name。
 * 只有名称**未被管理员修改**时才使用翻译——判断方式是后端 name 等于默认语言（zh-CN）中同一个 key 的文案；
 * 改过名的内置节点与自定义节点在所有语言下都直接显示后端 name（改名立即生效）。
 */
export type MessageLookup = (id: string) => string | undefined;

export interface NamedPermission {
  code: string;
  name: string;
  type: API.PermissionType;
}

/** 依次尝试的翻译 key */
function candidateKeys(node: NamedPermission): string[] {
  const keys = [`permission.${node.code}`];
  if (node.type === 'action') {
    keys.push(
      `permission.action.${node.code.slice(node.code.lastIndexOf('.') + 1)}`,
    );
  }
  return keys;
}

/**
 * @param lookup 当前语言的文案
 * @param defaultLookup 默认语言（zh-CN）的文案，用于判断名称是否被修改过
 */
export function permissionName(
  node: NamedPermission,
  lookup: MessageLookup,
  defaultLookup: MessageLookup,
): string {
  for (const key of candidateKeys(node)) {
    const defaultName = defaultLookup(key);
    if (defaultName === undefined) continue;
    // 默认文案存在但与后端 name 不同：管理员改过名称，以后端为准
    if (defaultName !== node.name) return node.name;
    return lookup(key) ?? node.name;
  }
  return node.name;
}

/** 返回新树：每个节点的 name 换成当前语言的名称 */
export function translatePermissionTree<
  T extends NamedPermission & { children?: T[] },
>(nodes: T[], lookup: MessageLookup, defaultLookup: MessageLookup): T[] {
  return nodes.map((node) => ({
    ...node,
    name: permissionName(node, lookup, defaultLookup),
    children: node.children
      ? translatePermissionTree(node.children, lookup, defaultLookup)
      : node.children,
  }));
}
