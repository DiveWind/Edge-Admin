import { Tag } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';

const COLORS: Record<API.PermissionType, string> = {
  directory: 'blue',
  menu: 'green',
  action: 'default',
};

/** 菜单树节点类型标签：目录 / 菜单 / 按钮（菜单管理页、角色授权树） */
const PermissionTypeTag: React.FC<{ type: API.PermissionType }> = ({
  type,
}) => {
  const t = useT();
  return (
    <Tag color={COLORS[type]} variant="outlined">
      {t(`component.permissionType.${type}`)}
    </Tag>
  );
};

export default PermissionTypeTag;
