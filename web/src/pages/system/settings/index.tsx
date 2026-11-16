import { PlusOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useQuery } from '@tanstack/react-query';
import { useAccess, useModel } from '@umijs/max';
import { Button, Space, Tag, Tooltip, Typography } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import SettingForm from './components/SettingForm';
import type { Setting } from './data';
import { deleteSetting, getSettingGroups, querySettings } from './service';
import { useSettingTypeEnum } from './useSettingTypeEnum';

/** 按类型展示参数值 */
const SettingValue: React.FC<{ setting: Setting }> = ({ setting }) => {
  const t = useT();
  const { type, value } = setting;
  if (value === null || value === '') return <>-</>;
  if (type === 'boolean') {
    return value ? (
      <Tag color="success">{t('common.yes')}</Tag>
    ) : (
      <Tag>{t('common.no')}</Tag>
    );
  }
  const text = type === 'json' ? JSON.stringify(value) : String(value);
  return (
    <Typography.Text code={type === 'json'} ellipsis={{ tooltip: text }}>
      {text}
    </Typography.Text>
  );
};

/** 系统参数：内置参数（is_system）不可改 key / type、不可删除 */
const SettingList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const typeEnum = useSettingTypeEnum();
  const actionRef = useRef<ActionType>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Setting>();

  const { data: groups = [], refetch: refetchGroups } = useQuery({
    queryKey: ['settings', 'groups'],
    queryFn: getSettingGroups,
  });

  const { initialState, setInitialState } = useModel('@@initialState');

  /** 参数变更后刷新列表、分组，并重新加载公开参数（站点名称等立即生效） */
  const changed = async () => {
    actionRef.current?.reload();
    refetchGroups();
    const site = await initialState?.fetchSiteSettings();
    if (site) setInitialState((state) => (state ? { ...state, site } : state));
  };

  const openForm = (record?: Setting) => {
    setEditing(record);
    setFormOpen(true);
  };

  const columns: ProColumns<Setting>[] = [
    {
      title: t('pages.system.settings.group'),
      dataIndex: 'group',
      valueType: 'select',
      fieldProps: {
        options: groups.map((group) => ({ label: group, value: group })),
      },
      width: 110,
      render: (_, record) => <Tag>{record.group}</Tag>,
    },
    {
      title: t('pages.system.settings.name'),
      dataIndex: 'name',
      width: 200,
      render: (_, record) => (
        <Space size={4}>
          {record.name}
          {record.is_system && (
            <Tooltip title={t('pages.system.settings.system.tooltip')}>
              <Tag color="blue">{t('pages.system.settings.isSystem')}</Tag>
            </Tooltip>
          )}
        </Space>
      ),
    },
    {
      title: t('pages.system.settings.key'),
      dataIndex: 'key',
      copyable: true,
      sorter: true,
      width: 240,
    },
    {
      title: t('pages.system.settings.value'),
      dataIndex: 'value',
      search: false,
      width: 200,
      render: (_, record) => <SettingValue setting={record} />,
    },
    {
      title: t('pages.system.settings.type'),
      dataIndex: 'type',
      valueEnum: typeEnum,
      search: false,
      width: 90,
    },
    {
      title: t('pages.system.settings.isPublic'),
      dataIndex: 'is_public',
      search: false,
      width: 80,
      render: (_, record) =>
        record.is_public ? (
          <Tag color="green">{t('common.yes')}</Tag>
        ) : (
          <Tag>{t('common.no')}</Tag>
        ),
    },
    {
      title: t('common.remark'),
      dataIndex: 'remark',
      key: 'remark',
      search: false,
      ellipsis: true,
      width: 200,
    },
    {
      title: t('common.updatedAt'),
      dataIndex: 'updated_at',
      key: 'updated_at',
      valueType: 'dateTime',
      search: false,
      width: 170,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 110,
      render: (_, record) => [
        access.can('system.setting.update') && (
          <Typography.Link key="edit" onClick={() => openForm(record)}>
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('system.setting.delete') && !record.is_system && (
          <DeleteAction
            key="delete"
            onConfirm={() => deleteSetting(record.id)}
            onSuccess={changed}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<Setting>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(querySettings)}
        // 备注、更新时间默认隐藏（列设置中可打开）
        columnsState={{
          defaultValue: {
            remark: { show: false },
            updated_at: { show: false },
          },
        }}
        toolBarRender={() => [
          access.can('system.setting.create') && (
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
      <SettingForm
        open={formOpen}
        onOpenChange={setFormOpen}
        record={editing}
        groups={groups}
        onSuccess={changed}
      />
    </PageContainer>
  );
};

export default SettingList;
