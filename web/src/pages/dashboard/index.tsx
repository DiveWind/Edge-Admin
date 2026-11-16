import {
  ApartmentOutlined,
  FileTextOutlined,
  LoginOutlined,
  SafetyOutlined,
  ThunderboltOutlined,
  UserOutlined,
} from '@ant-design/icons';
import type { ProColumns } from '@ant-design/pro-components';
import {
  PageContainer,
  ProCard,
  ProTable,
  StatisticCard,
} from '@ant-design/pro-components';
import { useQuery } from '@tanstack/react-query';
import { useModel } from '@umijs/max';
import { Tag, theme } from 'antd';
import dayjs from 'dayjs';
import type React from 'react';
import type { MessageId } from '@/utils/i18n';
import { useT } from '@/utils/i18n';
import {
  ArticleCategoryChart,
  DepartmentUserChart,
  LoginTrendChart,
} from './components/Charts';
import type { DashboardData, RecentOperation } from './data';
import { getDashboard } from './service';

const TOTALS: {
  key: keyof DashboardData['totals'];
  title: MessageId;
  icon: React.ReactNode;
}[] = [
  {
    key: 'users',
    title: 'pages.dashboard.totals.users',
    icon: <UserOutlined />,
  },
  {
    key: 'roles',
    title: 'pages.dashboard.totals.roles',
    icon: <SafetyOutlined />,
  },
  {
    key: 'departments',
    title: 'pages.dashboard.totals.departments',
    icon: <ApartmentOutlined />,
  },
  {
    key: 'articles',
    title: 'pages.dashboard.totals.articles',
    icon: <FileTextOutlined />,
  },
  {
    key: 'today_logins',
    title: 'pages.dashboard.totals.todayLogins',
    icon: <LoginOutlined />,
  },
  {
    key: 'today_operations',
    title: 'pages.dashboard.totals.todayOperations',
    icon: <ThunderboltOutlined />,
  },
];

const Dashboard: React.FC = () => {
  const t = useT();
  const { token } = theme.useToken();
  const { initialState } = useModel('@@initialState');
  const { data, isLoading } = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await getDashboard()).data,
  });

  const operationColumns: ProColumns<RecentOperation>[] = [
    {
      title: t('pages.dashboard.operations.user'),
      dataIndex: ['user', 'name'],
      width: 120,
    },
    {
      title: t('pages.dashboard.operations.action'),
      dataIndex: 'summary',
      ellipsis: true,
      render: (_, record) => (
        <>
          <Tag>{record.method}</Tag>
          {record.summary || record.path}
        </>
      ),
    },
    {
      title: t('pages.dashboard.operations.statusCode'),
      dataIndex: 'status_code',
      width: 90,
      render: (_, record) => (
        <Tag color={record.status_code >= 400 ? 'error' : 'success'}>
          {record.status_code}
        </Tag>
      ),
    },
    {
      title: t('pages.dashboard.operations.time'),
      dataIndex: 'created_at',
      width: 120,
      renderText: (value: string) => dayjs(value).fromNow(),
    },
  ];

  return (
    <PageContainer
      content={t('pages.dashboard.welcome', {
        name: initialState?.currentUser?.name ?? '',
      })}
    >
      <ProCard ghost gutter={[16, 16]} wrap>
        {TOTALS.map(({ key, title, icon }) => (
          <StatisticCard
            key={key}
            colSpan={{ xs: 12, md: 8, xl: 4 }}
            loading={isLoading}
            statistic={{
              title: t(title),
              value: data?.totals[key] ?? 0,
              icon: (
                <span style={{ fontSize: 28, color: token.colorPrimary }}>
                  {icon}
                </span>
              ),
            }}
          />
        ))}
        <ProCard
          title={t('pages.dashboard.loginTrend')}
          colSpan={{ xs: 24, xl: 16 }}
          loading={isLoading}
          headerBordered
        >
          <LoginTrendChart data={data?.login_trend ?? []} />
        </ProCard>
        <ProCard
          title={t('pages.dashboard.articlesByCategory')}
          colSpan={{ xs: 24, xl: 8 }}
          loading={isLoading}
          headerBordered
        >
          <ArticleCategoryChart data={data?.articles_by_category ?? []} />
        </ProCard>
        <ProCard
          title={t('pages.dashboard.usersByDepartment')}
          colSpan={{ xs: 24, xl: 12 }}
          loading={isLoading}
          headerBordered
        >
          <DepartmentUserChart data={data?.users_by_department ?? []} />
        </ProCard>
        <ProCard
          title={t('pages.dashboard.recentOperations')}
          colSpan={{ xs: 24, xl: 12 }}
          headerBordered
        >
          <ProTable<RecentOperation>
            rowKey="id"
            ghost
            size="small"
            search={false}
            options={false}
            toolBarRender={false}
            pagination={false}
            loading={isLoading}
            dataSource={data?.recent_operations ?? []}
            columns={operationColumns}
          />
        </ProCard>
      </ProCard>
    </PageContainer>
  );
};

export default Dashboard;
