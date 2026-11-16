import {
  CloudServerOutlined,
  ExclamationCircleOutlined,
  PaperClipOutlined,
  ReloadOutlined,
  UserOutlined,
} from '@ant-design/icons';
import type { ProDescriptionsItemProps } from '@ant-design/pro-components';
import {
  PageContainer,
  ProCard,
  ProDescriptions,
  StatisticCard,
} from '@ant-design/pro-components';
import { useQuery } from '@tanstack/react-query';
import { Button, Col, Flex, Progress, Row, Tag, Typography, theme } from 'antd';
import type React from 'react';
import { formatBytes } from '@/utils/format';
import type { MessageId } from '@/utils/i18n';
import { useT } from '@/utils/i18n';
import type { SystemInfo } from './data';
import { getSystemInfo } from './service';

type Section = 'app' | 'runtime' | 'drivers';

const METRICS: {
  key: keyof SystemInfo['metrics'];
  icon: React.ReactNode;
}[] = [
  { key: 'users', icon: <UserOutlined /> },
  { key: 'online_sessions', icon: <CloudServerOutlined /> },
  { key: 'attachments', icon: <PaperClipOutlined /> },
  { key: 'failed_jobs', icon: <ExclamationCircleOutlined /> },
];

/** 系统信息：只读展示，不含主机、账号、密钥等敏感配置 */
const SystemInfoPage: React.FC = () => {
  const t = useT();
  const { token } = theme.useToken();
  const { data, isLoading, isFetching, refetch } = useQuery({
    queryKey: ['system-info'],
    queryFn: async () => (await getSystemInfo()).data,
  });

  const label = (section: Section, field: string) =>
    t(`pages.monitor.systemInfo.${section}.${field}` as MessageId);

  const onOff = (value: boolean) =>
    value ? (
      <Tag color="warning">{t('pages.monitor.systemInfo.yes')}</Tag>
    ) : (
      <Tag>{t('pages.monitor.systemInfo.no')}</Tag>
    );

  const appColumns: ProDescriptionsItemProps<SystemInfo['app']>[] = [
    { title: label('app', 'name'), dataIndex: 'name' },
    { title: label('app', 'version'), dataIndex: 'version' },
    {
      title: label('app', 'env'),
      dataIndex: 'env',
      render: (_, record) => (
        <Tag color={record.env === 'production' ? 'success' : 'processing'}>
          {record.env}
        </Tag>
      ),
    },
    {
      title: label('app', 'debug'),
      dataIndex: 'debug',
      render: (_, record) => onOff(record.debug),
    },
    { title: label('app', 'locale'), dataIndex: 'locale' },
    { title: label('app', 'timezone'), dataIndex: 'timezone' },
  ];

  const runtimeColumns: ProDescriptionsItemProps<SystemInfo['runtime']>[] = [
    { title: label('runtime', 'php_version'), dataIndex: 'php_version' },
    {
      title: label('runtime', 'laravel_version'),
      dataIndex: 'laravel_version',
    },
    { title: label('runtime', 'os'), dataIndex: 'os', ellipsis: true },
    { title: label('runtime', 'sapi'), dataIndex: 'sapi' },
    {
      title: label('runtime', 'memory_limit'),
      dataIndex: 'memory_limit',
      renderText: (value: string | null) =>
        value === '-1' ? t('pages.monitor.systemInfo.unlimited') : value,
    },
    {
      title: label('runtime', 'upload_max_filesize'),
      dataIndex: 'upload_max_filesize',
    },
    { title: label('runtime', 'post_max_size'), dataIndex: 'post_max_size' },
    {
      title: label('runtime', 'server_time'),
      dataIndex: 'server_time',
      valueType: 'dateTime',
    },
  ];

  const driverColumns: ProDescriptionsItemProps<SystemInfo['drivers']>[] = [
    {
      title: label('drivers', 'database'),
      dataIndex: 'database',
      render: (_, record) =>
        [record.database.driver, record.database.version]
          .filter(Boolean)
          .join(' '),
    },
    { title: label('drivers', 'cache'), dataIndex: 'cache' },
    { title: label('drivers', 'queue'), dataIndex: 'queue' },
    { title: label('drivers', 'session'), dataIndex: 'session' },
    { title: label('drivers', 'filesystem'), dataIndex: 'filesystem' },
    { title: label('drivers', 'upload_disk'), dataIndex: 'upload_disk' },
  ];

  const storage = data?.storage;
  const diskUsed =
    storage?.disk_total_bytes && storage.disk_free_bytes !== null
      ? storage.disk_total_bytes - storage.disk_free_bytes
      : null;
  const diskPercent =
    diskUsed !== null && storage?.disk_total_bytes
      ? Math.round((diskUsed / storage.disk_total_bytes) * 100)
      : null;

  const descriptionsColumn = { xs: 1, sm: 2 };

  return (
    <PageContainer
      extra={
        <Button
          icon={<ReloadOutlined />}
          loading={isFetching}
          onClick={() => refetch()}
        >
          {t('pages.monitor.systemInfo.refresh')}
        </Button>
      }
    >
      <Row gutter={[16, 16]}>
        {METRICS.map(({ key, icon }) => (
          <Col key={key} xs={12} lg={6}>
            <StatisticCard
              loading={isLoading}
              statistic={{
                title: t(`pages.monitor.systemInfo.metrics.${key}`),
                value: data?.metrics[key] ?? 0,
                icon: (
                  <span style={{ fontSize: 28, color: token.colorPrimary }}>
                    {icon}
                  </span>
                ),
              }}
            />
          </Col>
        ))}
        <Col xs={24} xl={12}>
          <ProCard
            title={t('pages.monitor.systemInfo.app')}
            headerBordered
            loading={isLoading}
          >
            <ProDescriptions
              column={descriptionsColumn}
              dataSource={data?.app}
              columns={appColumns}
            />
          </ProCard>
        </Col>
        <Col xs={24} xl={12}>
          <ProCard
            title={t('pages.monitor.systemInfo.drivers')}
            headerBordered
            loading={isLoading}
          >
            <ProDescriptions
              column={descriptionsColumn}
              dataSource={data?.drivers}
              columns={driverColumns}
            />
          </ProCard>
        </Col>
        <Col xs={24} xl={12}>
          <ProCard
            title={t('pages.monitor.systemInfo.runtime')}
            headerBordered
            loading={isLoading}
          >
            <ProDescriptions
              column={descriptionsColumn}
              dataSource={data?.runtime}
              columns={runtimeColumns}
            />
          </ProCard>
        </Col>
        <Col xs={24} xl={12}>
          <ProCard
            title={t('pages.monitor.systemInfo.storage')}
            headerBordered
            loading={isLoading}
          >
            <Flex vertical gap="middle">
              <div>
                <Typography.Text type="secondary">
                  {t('pages.monitor.systemInfo.storage.disk')}
                </Typography.Text>
                <Progress
                  percent={diskPercent ?? 0}
                  status={
                    diskPercent !== null && diskPercent >= 90
                      ? 'exception'
                      : 'normal'
                  }
                />
                <Typography.Text type="secondary">
                  {diskUsed === null
                    ? '-'
                    : t('pages.monitor.systemInfo.storage.diskDetail', {
                        used: formatBytes(diskUsed),
                        total: formatBytes(storage?.disk_total_bytes),
                        free: formatBytes(storage?.disk_free_bytes),
                      })}
                </Typography.Text>
              </div>
              <ProDescriptions
                column={descriptionsColumn}
                dataSource={storage}
                columns={[
                  {
                    title: t('pages.monitor.systemInfo.storage.database'),
                    dataIndex: 'database_bytes',
                    renderText: (value: number | null) => formatBytes(value),
                  },
                  {
                    title: t('pages.monitor.systemInfo.storage.attachments'),
                    dataIndex: 'attachments_bytes',
                    renderText: (value: number | null) => formatBytes(value),
                  },
                ]}
              />
            </Flex>
          </ProCard>
        </Col>
      </Row>
    </PageContainer>
  );
};

export default SystemInfoPage;
