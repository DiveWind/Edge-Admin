import type { ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useQuery } from '@tanstack/react-query';
import { Tag } from 'antd';
import type React from 'react';
import { useEffect, useMemo, useState } from 'react';
import { usePermissionTranslator } from '@/hooks/usePermissionName';
import { getPermissionTree } from '@/services/permission';
import { useT } from '@/utils/i18n';
import { collectParentIds, pruneEmptyChildren } from '@/utils/tree';

/** 权限点在后端配置中声明并同步，这里只读展示；名称按 code 翻译（缺失回退后端 name） */
const PermissionList: React.FC = () => {
  const t = useT();
  const translatePermissions = usePermissionTranslator();
  const [expandedKeys, setExpandedKeys] = useState<readonly React.Key[]>([]);

  const { data, isFetching, refetch } = useQuery({
    queryKey: ['permissions', 'tree'],
    queryFn: async () => (await getPermissionTree()).data,
  });

  useEffect(() => {
    if (data) setExpandedKeys(collectParentIds(data));
  }, [data]);

  // 切换语言时只重新翻译，不重新请求
  const dataSource = useMemo(
    () => pruneEmptyChildren(translatePermissions(data ?? [])),
    [data, translatePermissions],
  );

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
        loading={isFetching}
        dataSource={dataSource}
        options={{ reload: () => refetch() }}
        expandable={{
          expandedRowKeys: expandedKeys,
          onExpandedRowsChange: setExpandedKeys,
        }}
      />
    </PageContainer>
  );
};

export default PermissionList;
