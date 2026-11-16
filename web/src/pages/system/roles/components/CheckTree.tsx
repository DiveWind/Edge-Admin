import type { TreeProps } from 'antd';
import { Empty, Tree } from 'antd';
import type React from 'react';
import { useMemo } from 'react';
import { collectLeafIds, toTreeData } from '@/utils/tree';

export interface CheckTreeProps {
  treeData: Parameters<typeof toTreeData>[0];
  value?: API.Id[];
  onChange?: (value: API.Id[]) => void;
  /**
   * 父子不关联（部门：勾选哪个就是哪个）。
   * 默认父子关联（权限：value 同时包含全选与半选的父节点，回显时只用叶子节点驱动勾选状态）。
   */
  strict?: boolean;
  disabled?: boolean;
}

/** 可勾选的树形表单控件，value 为 id 数组，可直接放进 ProForm.Item */
const CheckTree: React.FC<CheckTreeProps> = ({
  treeData,
  value = [],
  onChange,
  strict = false,
  disabled,
}) => {
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
    onChange?.(keys as API.Id[]);
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
    />
  );
};

export default CheckTree;
