import { useIntl } from '@umijs/max';
import { useCallback } from 'react';
import zhCN from '@/locales/zh-CN';
import type { MessageLookup, NamedPermission } from '@/utils/permission';
import { permissionName, translatePermissionTree } from '@/utils/permission';

/** 默认语言（zh-CN）文案：判断菜单 / 权限点名称是否被管理员改过（见 utils/permission） */
export const defaultMessageLookup: MessageLookup = (id) => {
  const message = (zhCN as Record<string, string>)[id];
  return typeof message === 'string' ? message : undefined;
};

/** 当前语言的翻译函数：`const name = usePermissionName(); name(node)` */
export function usePermissionName() {
  const intl = useIntl();
  return useCallback(
    (node: NamedPermission) => {
      const lookup: MessageLookup = (id) => {
        const message = intl.messages[id];
        return typeof message === 'string' ? message : undefined;
      };
      return permissionName(node, lookup, defaultMessageLookup);
    },
    [intl],
  );
}

/** 当前语言下翻译权限树（见 utils/permission）：`const translate = usePermissionTranslator(); translate(tree)` */
export function usePermissionTranslator() {
  const intl = useIntl();
  return useCallback(
    <T extends NamedPermission & { children?: T[] }>(nodes: T[]) => {
      const lookup: MessageLookup = (id) => {
        const message = intl.messages[id];
        return typeof message === 'string' ? message : undefined;
      };
      return translatePermissionTree(nodes, lookup, defaultMessageLookup);
    },
    [intl],
  );
}
