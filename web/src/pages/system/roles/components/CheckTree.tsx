import type { TreeProps } from 'antd';
import { Empty, Tree } from 'antd';
import type React from 'react';
import { useMemo } from 'react';
import { collectLeafIds, toTreeData } from '@/utils/tree';

interface CheckTreeNode {
  id: API.Id;
  name: string;
  children?: CheckTreeNode[];
}

export interface CheckTreeProps<T extends CheckTreeNode> {
  treeData: T[];
  value?: API.Id[];
  onChange?: (value: API.Id[]) => void;
  /**
   * 父子不关联（部门：勾选哪个就是哪个）。
   * 默认父子关联（权限：value 同时包含全选与半选的父节点，回显时只用叶子节点驱动勾选状态）。
   */
  strict?: boolean;
  disabled?: boolean;
  /** 自定义节点标题（如权限树按类型加 Tag） */
  renderTitle?: (node: T) => React.ReactNode;
}

function indexById<T extends CheckTreeNode>(
  nodes: T[],
  map = new Map<API.Id, T>(),
) {
  for (const node of nodes) {
    map.set(node.id, node);
    indexById((node.children ?? []) as T[], map);
  }
  return map;
}

/**
 * 可勾选的树形表单控件，value 为 id 数组，可直接放进 ProForm.Item。
 * value 中不在树里的 id（如已停用、未下发的权限节点）原样保留，保存时不会被丢掉。
 */
function CheckTree<T extends CheckTreeNode>({
  treeData,
  value = [],
  onChange,
  strict = false,
  disabled,
  renderTitle,
}: CheckTreeProps<T>) {
  const byId = useMemo(() => indexById(treeData), [treeData]);
  const leafIds = useMemo(() => new Set(collectLeafIds(treeData)), [treeData]);
  const nodes = useMemo(() => toTreeData(treeData), [treeData]);

  if (!treeData.length) {
    return <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} />;
  }

  const checkedKeys: TreeProps['checkedKeys'] = strict
    ? { checked: value, halfChecked: [] }
    : value.filter((id) => leafIds.has(id));

  const onCheck: TreeProps['onCheck'] = (checked, info) => {
    const keys = Array.isArray(checked)
      ? [...checked, ...(info.halfCheckedKeys ?? [])]
      : checked.checked;
    const outside = value.filter((id) => !byId.has(id));
    onChange?.([...(keys as API.Id[]), ...outside]);
  };

  return (
    <Tree
      checkable
      selectable={false}
      defaultExpandAll
      height={320}
      disabled={disabled}
      checkStrictly={strict}
      treeData={nodes}
      checkedKeys={checkedKeys}
      onCheck={onCheck}
      titleRender={
        renderTitle
          ? (data) => {
              const node = byId.get(data.key as API.Id);
              return node ? renderTitle(node) : null;
            }
          : undefined
      }
    />
  );
}

export default CheckTree;
