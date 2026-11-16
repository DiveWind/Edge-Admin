import {
  ApartmentOutlined,
  CloudServerOutlined,
  FileTextOutlined,
  LoginOutlined,
  PaperClipOutlined,
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
import { Col, Row, Tag, theme } from 'antd';
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
  {
    key: 'online_sessions',
    title: 'pages.dashboard.totals.onlineSessions',
    icon: <CloudServerOutlined />,
  },
  {
    key: 'attachments',
    title: 'pages.dashboard.totals.attachments',
    icon: <PaperClipOutlined />,
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
          <Tag>{record.method}</Tag> {record.summary || record.path}
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
      {/*
        栅格用 Row/Col（纯 CSS 断点），首屏即是最终布局；图表宽度由 ChartBox 跟随容器。
        8 张统计卡：手机 / 平板 2 列、桌面 4 列（两行）、超宽屏 8 列（一行）
      */}
      <Row gutter={[16, 16]}>
        {TOTALS.map(({ key, title, icon }) => (
          <Col key={key} xs={12} xl={6} xxl={3}>
            <StatisticCard
              // 标题换行时同一行的卡片保持等高
              style={{ height: '100%' }}
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
          </Col>
        ))}
        <Col xs={24} xl={16}>
          <ProCard
            title={t('pages.dashboard.loginTrend')}
            loading={isLoading}
            headerBordered
          >
            <LoginTrendChart data={data?.login_trend ?? []} />
          </ProCard>
        </Col>
        <Col xs={24} xl={8}>
          <ProCard
            title={t('pages.dashboard.articlesByCategory')}
            loading={isLoading}
            headerBordered
          >
            <ArticleCategoryChart data={data?.articles_by_category ?? []} />
          </ProCard>
        </Col>
        <Col xs={24} xl={12}>
          <ProCard
            title={t('pages.dashboard.usersByDepartment')}
            loading={isLoading}
            headerBordered
          >
            <DepartmentUserChart data={data?.users_by_department ?? []} />
          </ProCard>
        </Col>
        <Col xs={24} xl={12}>
          <ProCard title={t('pages.dashboard.recentOperations')} headerBordered>
            <ProTable<RecentOperation>
              rowKey="id"
              ghost
              size="small"
              search={false}
              options={false}
              toolBarRender={false}
              pagination={false}
              // 窄屏时在卡片内横向滚动，避免省略列被挤没
              scroll={{ x: 520 }}
              loading={isLoading}
              dataSource={data?.recent_operations ?? []}
              columns={operationColumns}
            />
          </ProCard>
        </Col>
      </Row>
    </PageContainer>
  );
};

export default Dashboard;
