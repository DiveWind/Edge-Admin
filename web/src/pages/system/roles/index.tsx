import { PlusOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { Button, Typography } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import { useDataScopeEnum, useStatusEnum } from '@/hooks/useValueEnums';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import RoleForm from './components/RoleForm';
import type { Role } from './data';
import { deleteRole, queryRoles } from './service';

const RoleList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const statusEnum = useStatusEnum();
  const dataScopeEnum = useDataScopeEnum();
  const actionRef = useRef<ActionType>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Role>();

  const reload = () => actionRef.current?.reload();

  const openForm = (record?: Role) => {
    setEditing(record);
    setFormOpen(true);
  };

  const columns: ProColumns<Role>[] = [
    { title: t('pages.system.roles.name'), dataIndex: 'name' },
    { title: t('pages.system.roles.code'), dataIndex: 'code', copyable: true },
    {
      title: t('pages.system.roles.dataScope'),
      dataIndex: 'data_scope',
      valueEnum: dataScopeEnum,
      search: false,
    },
    {
      title: t('common.sort'),
      dataIndex: 'sort',
      search: false,
      sorter: true,
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: statusEnum,
    },
    {
      title: t('pages.system.roles.usersCount'),
      dataIndex: 'users_count',
      search: false,
    },
    {
      title: t('common.remark'),
      dataIndex: 'remark',
      search: false,
      ellipsis: true,
      width: 200,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'created_at',
      valueType: 'dateTime',
      width: 170,
      search: false,
      sorter: true,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 140,
      render: (_, record) => [
        access.can('system.role.update') && (
          <Typography.Link key="edit" onClick={() => openForm(record)}>
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('system.role.delete') && (
          <DeleteAction
            key="delete"
            // 超级管理员角色、已分配用户的角色不可删除
            disabled={record.is_super_admin || record.users_count > 0}
            onConfirm={() => deleteRole(record.id)}
            onSuccess={reload}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<Role>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryRoles)}
        toolBarRender={() => [
          access.can('system.role.create') && (
            <Button
              key="create"
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => openForm()}
            >
              {t('common.create')}
            </Button>
          ),
        ]}
      />
      <RoleForm
        open={formOpen}
        onOpenChange={setFormOpen}
        record={editing}
        onSuccess={reload}
      />
    </PageContainer>
  );
};

export default RoleList;
