import { ProDescriptions } from '@ant-design/pro-components';
import { Drawer, Tag } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';
import type { OperationLog } from '../data';
import { getOperationLog, methodColor } from '../service';
import StatusCodeTag from './StatusCodeTag';

interface OperationLogDetailProps {
  id?: API.Id;
  onClose: () => void;
}

const OperationLogDetail: React.FC<OperationLogDetailProps> = ({
  id,
  onClose,
}) => {
  const t = useT();

  return (
    <Drawer
      title={t('pages.monitor.operationLogs.detail')}
      size={640}
      open={id !== undefined}
      onClose={onClose}
      destroyOnHidden
    >
      <ProDescriptions<OperationLog>
        column={1}
        bordered
        size="small"
        params={{ id }}
        request={async () => {
          if (id === undefined) return { data: undefined, success: false };
          const { data } = await getOperationLog(id);
          return { data, success: true };
        }}
        columns={[
          {
            title: t('pages.monitor.operationLogs.user'),
            dataIndex: ['user', 'name'],
          },
          {
            title: t('pages.monitor.operationLogs.summary'),
            dataIndex: 'summary',
          },
          {
            title: t('pages.monitor.operationLogs.method'),
            dataIndex: 'method',
            render: (_, record) => (
              <Tag color={methodColor[record.method]}>{record.method}</Tag>
            ),
          },
          {
            title: t('pages.monitor.operationLogs.path'),
            dataIndex: 'path',
            copyable: true,
          },
          {
            title: t('pages.monitor.operationLogs.routeName'),
            dataIndex: 'route_name',
          },
          {
            title: t('pages.monitor.operationLogs.statusCode'),
            dataIndex: 'status_code',
            render: (_, record) => <StatusCodeTag code={record.status_code} />,
          },
          {
            title: t('pages.monitor.operationLogs.duration'),
            dataIndex: 'duration_ms',
            renderText: (value: number) => `${value} ms`,
          },
          { title: t('pages.monitor.operationLogs.ip'), dataIndex: 'ip' },
          {
            title: t('pages.monitor.operationLogs.userAgent'),
            dataIndex: 'user_agent',
          },
          {
            title: t('pages.monitor.operationLogs.operatedAt'),
            dataIndex: 'created_at',
            valueType: 'dateTime',
          },
          {
            title: t('pages.monitor.operationLogs.payload'),
            dataIndex: 'payload',
            valueType: 'jsonCode',
            // jsonCode 只格式化字符串，先序列化
            renderText: (payload: OperationLog['payload']) =>
              JSON.stringify(payload ?? {}),
          },
        ]}
      />
    </Drawer>
  );
};

export default OperationLogDetail;
