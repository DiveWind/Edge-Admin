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
import { toListQuery } from '@/utils/table';
import { collectParentIds } from '@/utils/tree';
import type { DepartmentFormTarget } from './components/DepartmentForm';
import DepartmentForm from './components/DepartmentForm';
import type { Department } from './data';
import { deleteDepartment, queryDepartments } from './service';

const DepartmentList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const statusEnum = useStatusEnum();
  const actionRef = useRef<ActionType>(null);
  const [expandedKeys, setExpandedKeys] = useState<readonly React.Key[]>([]);
  const [formTarget, setFormTarget] = useState<DepartmentFormTarget>();

  const reload = () => actionRef.current?.reload();

  const columns: ProColumns<Department>[] = [
    { title: t('pages.system.departments.name'), dataIndex: 'name' },
    {
      title: t('pages.system.departments.leader'),
      dataIndex: ['leader', 'name'],
      search: false,
    },
    { title: t('common.sort'), dataIndex: 'sort', search: false },
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
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 200,
      render: (_, record) => [
        access.can('system.department.create') && (
          <Typography.Link
            key="child"
            onClick={() =>
              setFormTarget({ mode: 'create', parentId: record.id })
            }
          >
            {t('pages.system.departments.addChild')}
          </Typography.Link>
        ),
        access.can('system.department.update') && (
          <Typography.Link
            key="edit"
            onClick={() => setFormTarget({ mode: 'edit', record })}
          >
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('system.department.delete') && (
          <DeleteAction
            key="delete"
            title={t('pages.system.departments.deleteConfirm')}
            onConfirm={() => deleteDepartment(record.id)}
            onSuccess={reload}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<Department>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        pagination={false}
        expandable={{
          expandedRowKeys: expandedKeys,
          onExpandedRowsChange: setExpandedKeys,
        }}
        request={async (params) => {
          const { data } = await queryDepartments(toListQuery(params));
          setExpandedKeys(collectParentIds(data));
          return { data, success: true };
        }}
        toolBarRender={() => [
          access.can('system.department.create') && (
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
      <DepartmentForm
        target={formTarget}
        onClose={() => setFormTarget(undefined)}
        onSuccess={reload}
      />
    </PageContainer>
  );
};

export default DepartmentList;
