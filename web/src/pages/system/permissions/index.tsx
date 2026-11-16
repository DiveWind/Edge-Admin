import { ExportOutlined, PlusOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useQueryClient } from '@tanstack/react-query';
import { useAccess } from '@umijs/max';
import { Button, Space, Switch, Tag, Tooltip, Typography } from 'antd';
import type React from 'react';
import { useMemo, useRef, useState } from 'react';
import { DeleteAction, MenuIcon, PermissionTypeTag } from '@/components';
import { usePermissionName } from '@/hooks/usePermissionName';
import { useRefreshAccess } from '@/hooks/useRefreshAccess';
import { useStatusEnum } from '@/hooks/useValueEnums';
import { useT } from '@/utils/i18n';
import { collectParentIds, pruneEmptyChildren } from '@/utils/tree';
import type { PermissionFormTarget } from './components/PermissionForm';
import PermissionForm from './components/PermissionForm';
import type { Permission, PermissionQuery } from './data';
import {
  deletePermission,
  queryPermissions,
  toPayload,
  updatePermission,
} from './service';
import { allowedChildTypes } from './tree';

/** 所有目录节点 id（默认展开到菜单一级） */
const collectDirectoryIds = (nodes: Permission[]): API.Id[] =>
  nodes.flatMap((node) =>
    node.type === 'directory'
      ? [node.id, ...collectDirectoryIds(node.children ?? [])]
      : [],
  );

/**
 * 菜单管理：目录 / 菜单 / 按钮组成的权限树，侧边菜单与按钮权限都以这里为准。
 * 保存后刷新当前用户的权限码与侧边菜单（useRefreshAccess），改名 / 图标 / 排序 / 显隐即时生效。
 */
const PermissionList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const statusEnum = useStatusEnum();
  const displayName = usePermissionName();
  const refreshAccess = useRefreshAccess();
  const queryClient = useQueryClient();
  const actionRef = useRef<ActionType>(null);
  const [expandedKeys, setExpandedKeys] = useState<readonly React.Key[]>([]);
  const [formTarget, setFormTarget] = useState<PermissionFormTarget>();
  const [toggling, setToggling] = useState<string>();
  const canUpdate = access.can('system.permission.update');

  const typeEnum = useMemo(
    () => ({
      directory: { text: t('component.permissionType.directory') },
      menu: { text: t('component.permissionType.menu') },
      action: { text: t('component.permissionType.action') },
    }),
    [t],
  );

  const changed = () => {
    actionRef.current?.reload();
    queryClient.invalidateQueries({ queryKey: ['permissions'] });
    queryClient.invalidateQueries({ queryKey: ['options', 'permissions'] });
    refreshAccess();
  };

  /** 列表上快捷切换显示 / 状态（提交完整字段） */
  const toggle = async (
    record: Permission,
    field: 'is_visible' | 'status',
    checked: boolean,
  ) => {
    setToggling(`${record.id}-${field}`);
    try {
      await updatePermission(
        record.id,
        toPayload({
          ...record,
          ...(field === 'is_visible'
            ? { is_visible: checked }
            : { status: checked ? 'active' : 'disabled' }),
        }),
      );
      changed();
    } finally {
      setToggling(undefined);
    }
  };

  const columns: ProColumns<Permission>[] = [
    {
      title: t('pages.system.permissions.name'),
      dataIndex: 'name',
      width: 240,
      render: (_, record) => (
        <Space size={6}>
          {record.type !== 'action' && <MenuIcon name={record.icon} />}
          {displayName(record)}
          {record.is_system && record.type !== 'action' && (
            <Tooltip title={t('pages.system.permissions.builtin.tooltip')}>
              <Tag>{t('pages.system.permissions.builtin')}</Tag>
            </Tooltip>
          )}
        </Space>
      ),
    },
    {
      title: t('pages.system.permissions.type'),
      dataIndex: 'type',
      valueType: 'select',
      valueEnum: typeEnum,
      width: 80,
      render: (_, record) => <PermissionTypeTag type={record.type} />,
    },
    {
      title: t('pages.system.permissions.icon'),
      dataIndex: 'icon',
      key: 'icon',
      search: false,
      width: 180,
      render: (_, record) =>
        record.icon ? (
          <Typography.Text type="secondary">{record.icon}</Typography.Text>
        ) : (
          '-'
        ),
    },
    {
      title: t('pages.system.permissions.path'),
      dataIndex: 'path',
      search: false,
      width: 170,
      ellipsis: true,
      render: (_, record) =>
        record.path ? (
          <Space size={4}>
            {record.path}
            {record.is_external && <ExportOutlined />}
          </Space>
        ) : (
          '-'
        ),
    },
    {
      title: t('pages.system.permissions.code'),
      dataIndex: 'code',
      copyable: true,
      width: 200,
      ellipsis: true,
    },
    {
      title: t('common.sort'),
      dataIndex: 'sort',
      search: false,
      width: 60,
    },
    {
      title: t('pages.system.permissions.isVisible'),
      dataIndex: 'is_visible',
      search: false,
      width: 70,
      render: (_, record) =>
        record.type === 'action' ? (
          '-'
        ) : (
          <Switch
            size="small"
            aria-label={t('pages.system.permissions.isVisible')}
            checked={record.is_visible}
            disabled={!canUpdate}
            loading={toggling === `${record.id}-is_visible`}
            onChange={(checked) =>
              toggle(record, 'is_visible', checked).catch(() => undefined)
            }
          />
        ),
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: statusEnum,
      width: 70,
      render: (_, record) => (
        <Switch
          size="small"
          aria-label={t('common.status')}
          checked={record.status === 'active'}
          disabled={!canUpdate}
          loading={toggling === `${record.id}-status`}
          onChange={(checked) =>
            toggle(record, 'status', checked).catch(() => undefined)
          }
        />
      ),
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 170,
      render: (_, record) => [
        access.can('system.permission.create') &&
          allowedChildTypes(record.type).length > 0 && (
            <Typography.Link
              key="child"
              onClick={() => setFormTarget({ mode: 'create', parent: record })}
            >
              {t('pages.system.permissions.addChild')}
            </Typography.Link>
          ),
        canUpdate && (
          <Typography.Link
            key="edit"
            onClick={() => setFormTarget({ mode: 'edit', record })}
          >
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('system.permission.delete') && !record.is_system && (
          <DeleteAction
            key="delete"
            title={t('pages.system.permissions.deleteConfirm')}
            onConfirm={() => deletePermission(record.id)}
            onSuccess={changed}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer content={t('pages.system.permissions.description')}>
      <ProTable<Permission, PermissionQuery>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        pagination={false}
        // 图标名默认隐藏（名称前已有图标预览，列设置中可打开）
        columnsState={{ defaultValue: { icon: { show: false } } }}
        expandable={{
          expandedRowKeys: expandedKeys,
          onExpandedRowsChange: setExpandedKeys,
        }}
        request={async (params) => {
          const { name, code, type, status } = params;
          const { data } = await queryPermissions({ name, code, type, status });
          // 默认展开目录，菜单下的按钮折叠（有过滤条件时全部展开，便于看到匹配项）
          const filtered = Boolean(name || code || type || status);
          setExpandedKeys(
            filtered ? collectParentIds(data) : collectDirectoryIds(data),
          );
          return { data: pruneEmptyChildren(data), success: true };
        }}
        toolBarRender={() => [
          access.can('system.permission.create') && (
            <Button
              key="create"
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => setFormTarget({ mode: 'create' })}
            >
              {t('common.create')}
            </Button>
          ),
        ]}
      />
      <PermissionForm
        target={formTarget}
        onClose={() => setFormTarget(undefined)}
        onSuccess={changed}
      />
    </PageContainer>
  );
};

export default PermissionList;
