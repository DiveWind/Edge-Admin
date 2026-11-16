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
import CategoryForm from './components/CategoryForm';
import type { Category } from './data';
import { deleteCategory, queryCategories } from './service';

const CategoryList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const statusEnum = useStatusEnum();
  const actionRef = useRef<ActionType>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Category>();

  const reload = () => actionRef.current?.reload();

  const openForm = (record?: Category) => {
    setEditing(record);
    setFormOpen(true);
  };

  const columns: ProColumns<Category>[] = [
    { title: t('pages.content.categories.name'), dataIndex: 'name' },
    { title: t('common.sort'), dataIndex: 'sort', search: false, sorter: true },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: statusEnum,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'created_at',
      valueType: 'dateTime',
      search: false,
      sorter: true,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 140,
      render: (_, record) => [
        access.can('content.category.update') && (
          <Typography.Link key="edit" onClick={() => openForm(record)}>
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('content.category.delete') && (
          <DeleteAction
            key="delete"
            title={t('pages.content.categories.deleteConfirm')}
            onConfirm={() => deleteCategory(record.id)}
            onSuccess={reload}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<Category>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryCategories)}
        toolBarRender={() => [
          access.can('content.category.create') && (
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
      <CategoryForm
        open={formOpen}
        onOpenChange={setFormOpen}
        record={editing}
        onSuccess={reload}
      />
    </PageContainer>
  );
};

export default CategoryList;
