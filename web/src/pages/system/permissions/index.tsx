import type { ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { Tag } from 'antd';
import type React from 'react';
import { useState } from 'react';
import { getPermissionTree } from '@/services/permission';
import { useT } from '@/utils/i18n';
import { collectParentIds } from '@/utils/tree';

/** 权限点在后端配置中声明并同步，这里只读展示 */
const PermissionList: React.FC = () => {
  const t = useT();
  const [expandedKeys, setExpandedKeys] = useState<readonly React.Key[]>([]);

  const columns: ProColumns<API.PermissionNode>[] = [
    { title: t('pages.system.permissions.name'), dataIndex: 'name' },
    {
      title: t('pages.system.permissions.code'),
      dataIndex: 'code',
      copyable: true,
    },
    {
      title: t('pages.system.permissions.type'),
      dataIndex: 'type',
      render: (_, record) =>
        record.type === 'menu' ? (
          <Tag color="blue">{t('pages.system.permissions.type.menu')}</Tag>
        ) : (
          <Tag color="green">{t('pages.system.permissions.type.action')}</Tag>
        ),
    },
    { title: t('common.sort'), dataIndex: 'sort' },
  ];

  return (
    <PageContainer content={t('pages.system.permissions.description')}>
      <ProTable<API.PermissionNode>
        rowKey="id"
        columns={columns}
        search={false}
        pagination={false}
        expandable={{
          expandedRowKeys: expandedKeys,
          onExpandedRowsChange: setExpandedKeys,
        }}
        request={async () => {
          const { data } = await getPermissionTree();
          setExpandedKeys(collectParentIds(data));
          return { data, success: true };
        }}
      />
    </PageContainer>
  );
};

export default PermissionList;
