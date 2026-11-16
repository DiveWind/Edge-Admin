import { PlusOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { Button, Typography } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import { useStatusEnum } from '@/hooks/useValueEnums';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import PositionForm from './components/PositionForm';
import type { Position } from './data';
import { deletePosition, queryPositions } from './service';

const PositionList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const statusEnum = useStatusEnum();
  const actionRef = useRef<ActionType>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Position>();

  const reload = () => actionRef.current?.reload();

  const openForm = (record?: Position) => {
    setEditing(record);
    setFormOpen(true);
  };

  const columns: ProColumns<Position>[] = [
    { title: t('pages.system.positions.name'), dataIndex: 'name' },
    {
      title: t('pages.system.positions.code'),
      dataIndex: 'code',
      copyable: true,
    },
    { title: t('common.sort'), dataIndex: 'sort', search: false, sorter: true },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: statusEnum,
    },
    {
      title: t('common.remark'),
      dataIndex: 'remark',
      search: false,
      ellipsis: true,
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
        access.can('system.position.update') && (
          <Typography.Link key="edit" onClick={() => openForm(record)}>
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('system.position.delete') && (
          <DeleteAction
            key="delete"
            disabled={record.users_count > 0}
            onConfirm={() => deletePosition(record.id)}
            onSuccess={reload}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<Position>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryPositions)}
        toolBarRender={() => [
          access.can('system.position.create') && (
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
      <PositionForm
        open={formOpen}
        onOpenChange={setFormOpen}
        record={editing}
        onSuccess={reload}
      />
    </PageContainer>
  );
};

export default PositionList;
