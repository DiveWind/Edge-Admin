import { PlusOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useQueryClient } from '@tanstack/react-query';
import { useAccess } from '@umijs/max';
import { Button, Typography } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import { useStatusEnum } from '@/hooks/useValueEnums';
import { dictKeys } from '@/services/dict';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import DictItemDrawer from './components/DictItemDrawer';
import DictTypeForm from './components/DictTypeForm';
import type { DictType } from './data';
import { deleteDictType, queryDictTypes } from './service';

/** 数据字典：字典类型列表，点击"字典项"在抽屉中管理该类型的字典项 */
const DictList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const statusEnum = useStatusEnum();
  const queryClient = useQueryClient();
  const actionRef = useRef<ActionType>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<DictType>();
  const [viewing, setViewing] = useState<DictType>();

  /** 字典变更后：刷新列表，并让所有 useDict 重新加载 */
  const changed = () => {
    actionRef.current?.reload();
    queryClient.invalidateQueries({ queryKey: dictKeys.all });
  };

  const openForm = (record?: DictType) => {
    setEditing(record);
    setFormOpen(true);
  };

  const columns: ProColumns<DictType>[] = [
    {
      title: t('pages.system.dicts.name'),
      dataIndex: 'name',
      render: (_, record) => (
        <Typography.Link onClick={() => setViewing(record)}>
          {record.name}
        </Typography.Link>
      ),
    },
    {
      title: t('pages.system.dicts.code'),
      dataIndex: 'code',
      copyable: true,
      width: 200,
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: statusEnum,
    },
    {
      title: t('pages.system.dicts.itemsCount'),
      dataIndex: 'items_count',
      search: false,
    },
    {
      title: t('common.remark'),
      dataIndex: 'remark',
      search: false,
      ellipsis: true,
      width: 240,
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
      width: 180,
      render: (_, record) => [
        <Typography.Link key="items" onClick={() => setViewing(record)}>
          {t('pages.system.dicts.items')}
        </Typography.Link>,
        access.can('system.dict.update') && (
          <Typography.Link key="edit" onClick={() => openForm(record)}>
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('system.dict.delete') && (
          <DeleteAction
            key="delete"
            disabled={record.items_count > 0}
            title={t('pages.system.dicts.deleteConfirm')}
            onConfirm={() => deleteDictType(record.id)}
            onSuccess={changed}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<DictType>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryDictTypes)}
        toolBarRender={() => [
          access.can('system.dict.create') && (
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
      <DictTypeForm
        open={formOpen}
        onOpenChange={setFormOpen}
        record={editing}
        onSuccess={changed}
      />
      <DictItemDrawer
        dictType={viewing}
        onClose={() => setViewing(undefined)}
        onChange={changed}
      />
    </PageContainer>
  );
};

export default DictList;
