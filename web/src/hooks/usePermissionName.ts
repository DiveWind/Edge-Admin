import { useIntl } from '@umijs/max';
import { useCallback } from 'react';
import type { MessageLookup } from '@/utils/permission';
import { translatePermissionTree } from '@/utils/permission';

/** 当前语言下翻译权限树（见 utils/permission）：`const translate = usePermissionTranslator(); translate(tree)` */
export function usePermissionTranslator() {
  const intl = useIntl();
  return useCallback(
    (nodes: API.PermissionNode[]) => {
      const lookup: MessageLookup = (id) => {
        const message = intl.messages[id];
        return typeof message === 'string' ? message : undefined;
      };
      return translatePermissionTree(nodes, lookup);
    },
    [intl],
  );
}
