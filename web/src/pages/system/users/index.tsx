import { PlusOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProCard, ProTable } from '@ant-design/pro-components';
import { useQuery } from '@tanstack/react-query';
import { useAccess, useModel } from '@umijs/max';
import {
  App,
  Button,
  Col,
  Empty,
  Row,
  Space,
  Spin,
  Switch,
  Tag,
  Tree,
  Typography,
} from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import { useStatusEnum } from '@/hooks/useValueEnums';
import {
  getDepartmentOptions,
  getRoleOptions,
  toSelectOptions,
} from '@/services/options';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import { toTreeData } from '@/utils/tree';
import ResetPasswordForm from './components/ResetPasswordForm';
import UserForm from './components/UserForm';
import type { User } from './data';
import {
  deleteUser,
  getUser,
  queryUsers,
  toUserFormValues,
  updateUser,
} from './service';

/** 契约：id=1 的用户受保护，不可删除 / 禁用 */
const PROTECTED_USER_ID = 1;

const UserList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const { message } = App.useApp();
  const { initialState } = useModel('@@initialState');
  const statusEnum = useStatusEnum();
  const actionRef = useRef<ActionType>(null);

  const [departmentId, setDepartmentId] = useState<API.Id>();
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<User>();
  const [resetting, setResetting] = useState<User>();

  const { data: departments = [], isLoading: departmentsLoading } = useQuery({
    queryKey: ['options', 'departments'],
    queryFn: async () => (await getDepartmentOptions()).data,
  });

  const reload = () => actionRef.current?.reload();

  const isProtected = (record: User) =>
    record.id === PROTECTED_USER_ID ||
    record.id === initialState?.currentUser?.id;

  const openForm = (record?: User) => {
    setEditing(record);
    setFormOpen(true);
  };

  /** 契约没有单独的启停接口：取详情后带完整数据 PUT */
  const toggleStatus = async (record: User, checked: boolean) => {
    const { data } = await getUser(record.id);
    await updateUser(record.id, {
      ...toUserFormValues(data),
      status: checked ? 'active' : 'disabled',
    });
    message.success(t('common.updated'));
    reload();
  };

  const columns: ProColumns<User>[] = [
    {
      title: t('pages.system.users.username'),
      dataIndex: 'username',
    },
    {
      title: t('pages.system.users.name'),
      dataIndex: 'name',
    },
    {
      title: t('pages.system.users.phone'),
      dataIndex: 'phone',
    },
    {
      title: t('pages.system.users.department'),
      dataIndex: ['department', 'name'],
      search: false,
    },
    {
      title: t('pages.system.users.roles'),
      dataIndex: 'role_id',
      valueType: 'select',
      request: () => getRoleOptions().then(toSelectOptions),
      fieldProps: { showSearch: { optionFilterProp: 'label' } },
      render: (_, record) => (
        <Space size={[0, 4]} wrap>
          {record.roles.map((role) => (
            <Tag key={role.id} color="blue">
              {role.name}
            </Tag>
          ))}
        </Space>
      ),
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: statusEnum,
      render: (dom, record) =>
        access.can('system.user.update') ? (
          <Switch
            size="small"
            checked={record.status === 'active'}
            disabled={isProtected(record)}
            onChange={(checked) =>
              toggleStatus(record, checked).catch(() => undefined)
            }
          />
        ) : (
          dom
        ),
    },
    {
      title: t('pages.system.users.lastLoginAt'),
      dataIndex: 'last_login_at',
      valueType: 'dateTime',
      search: false,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'created_at',
      valueType: 'dateTime',
      search: false,
      sorter: true,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      fixed: 'right',
      width: 200,
      render: (_, record) => [
        access.can('system.user.update') && (
          <Typography.Link key="edit" onClick={() => openForm(record)}>
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('system.user.reset-password') && (
          <Typography.Link key="reset" onClick={() => setResetting(record)}>
            {t('pages.system.users.resetPassword')}
          </Typography.Link>
        ),
        access.can('system.user.delete') && (
          <DeleteAction
            key="delete"
            disabled={isProtected(record)}
            onConfirm={() => deleteUser(record.id)}
            onSuccess={reload}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={6} xxl={4}>
          <ProCard title={t('pages.system.users.departmentTree')}>
            <Spin spinning={departmentsLoading}>
              {departments.length ? (
                <Tree
                  blockNode
                  defaultExpandAll
                  treeData={toTreeData(departments)}
                  selectedKeys={departmentId ? [departmentId] : []}
                  onSelect={(keys) => setDepartmentId(keys[0] as API.Id)}
                />
              ) : (
                <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} />
              )}
            </Spin>
          </ProCard>
        </Col>
        <Col xs={24} lg={18} xxl={20}>
          <ProTable<User>
            rowKey="id"
            actionRef={actionRef}
            columns={columns}
            params={{ department_id: departmentId }}
            scroll={{ x: 1100 }}
            request={tableRequest(queryUsers)}
            toolBarRender={() => [
              access.can('system.user.create') && (
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
        </Col>
      </Row>
      <UserForm
        open={formOpen}
        onOpenChange={setFormOpen}
        record={editing}
        departments={departments}
        onSuccess={reload}
      />
      <ResetPasswordForm
        record={resetting}
        onClose={() => setResetting(undefined)}
      />
    </PageContainer>
  );
};

export default UserList;
