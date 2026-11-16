import { PlusOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { Button, Drawer, Tag, Typography } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import { useStatusEnum } from '@/hooks/useValueEnums';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import type { DictItem, DictType } from '../data';
import { deleteDictItem, queryDictItems } from '../service';
import DictItemForm from './DictItemForm';

interface DictItemDrawerProps {
  /** 为空时关闭 */
  dictType?: DictType;
  onClose: () => void;
  /** 字典项有增删改（刷新类型列表的项数、失效字典缓存） */
  onChange: () => void;
}

/** 某个字典类型下的字典项管理 */
const DictItemDrawer: React.FC<DictItemDrawerProps> = ({
  dictType,
  onClose,
  onChange,
}) => {
  const t = useT();
  const access = useAccess();
  const statusEnum = useStatusEnum();
  const actionRef = useRef<ActionType>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<DictItem>();

  const changed = () => {
    actionRef.current?.reload();
    onChange();
  };

  const openForm = (record?: DictItem) => {
    setEditing(record);
    setFormOpen(true);
  };

  const columns: ProColumns<DictItem>[] = [
    {
      title: t('pages.system.dicts.item.label'),
      dataIndex: 'label',
      render: (_, record) => (
        <Tag color={record.color || undefined}>{record.label}</Tag>
      ),
    },
    {
      title: t('pages.system.dicts.item.value'),
      dataIndex: 'value',
      copyable: true,
      search: false,
    },
    {
      title: t('pages.system.dicts.item.color'),
      dataIndex: 'color',
      search: false,
    },
    {
      title: t('common.sort'),
      dataIndex: 'sort',
      search: false,
      sorter: true,
      width: 80,
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: statusEnum,
      width: 100,
    },
    {
      title: t('common.remark'),
      dataIndex: 'remark',
      search: false,
      ellipsis: true,
      width: 160,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 120,
      render: (_, record) => [
        access.can('system.dict.update') && (
          <Typography.Link key="edit" onClick={() => openForm(record)}>
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('system.dict.delete') && (
          <DeleteAction
            key="delete"
            onConfirm={() => deleteDictItem(record.id)}
            onSuccess={changed}
          />
        ),
      ],
    },
  ];

  return (
    <Drawer
      open={Boolean(dictType)}
      onClose={onClose}
      size={880}
      destroyOnHidden
      title={
        dictType &&
        t('pages.system.dicts.items.title', {
          name: dictType.name,
          code: dictType.code,
        })
      }
    >
      {dictType && (
        <>
          <ProTable<DictItem>
            rowKey="id"
            actionRef={actionRef}
            columns={columns}
            ghost
            search={{ filterType: 'light' }}
            options={{ density: false, setting: false, reload: true }}
            request={tableRequest((query) =>
              queryDictItems({ ...query, dict_type_id: dictType.id }),
            )}
            toolBarRender={() => [
              access.can('system.dict.create') && (
                <Button
                  key="create"
                  type="primary"
                  icon={<PlusOutlined />}
                  onClick={() => openForm()}
                >
                  {t('pages.system.dicts.item.create')}
                </Button>
              ),
            ]}
          />
          <DictItemForm
            open={formOpen}
            onOpenChange={setFormOpen}
            dictTypeId={dictType.id}
            record={editing}
            onSuccess={changed}
          />
        </>
      )}
    </Drawer>
  );
};

export default DictItemDrawer;
