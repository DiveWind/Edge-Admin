import {
  ProDescriptions,
  ProForm,
  ProFormText,
} from '@ant-design/pro-components';
import { useModel } from '@umijs/max';
import { App, Col, Divider, Form, Row, Space, Tag } from 'antd';
import type React from 'react';
import { useState } from 'react';
import { ImageUpload } from '@/components';
import { useDataScopeEnum } from '@/hooks/useValueEnums';
import { updateProfile } from '@/services/auth';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';

/** 基本资料：只读账户信息 + 可编辑资料（`PUT /api/auth/profile`） */
const BasicSettings: React.FC<{ currentUser: API.CurrentUser }> = ({
  currentUser,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const { setInitialState } = useModel('@@initialState');
  const dataScopeEnum = useDataScopeEnum();
  const [form] = Form.useForm<API.ProfileParams>();
  // 只在挂载时取一次：保存后 currentUser 会更新，但表单已持有最新值
  const [initialValues] = useState<API.ProfileParams>(() => ({
    name: currentUser.name,
    email: currentUser.email,
    phone: currentUser.phone,
    avatar: currentUser.avatar,
  }));

  const handleFinish = (values: API.ProfileParams) =>
    submitForm(form, async () => {
      const { data } = await updateProfile(values);
      setInitialState((state) =>
        state ? { ...state, currentUser: data } : state,
      );
      message.success(t('common.updated'));
    });

  return (
    <>
      <ProDescriptions<API.CurrentUser>
        column={{ xs: 1, md: 2 }}
        dataSource={currentUser}
        columns={[
          { title: t('pages.account.username'), dataIndex: 'username' },
          {
            title: t('pages.account.department'),
            dataIndex: ['department', 'name'],
          },
          {
            title: t('pages.account.roles'),
            dataIndex: 'roles',
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
            title: t('pages.account.positions'),
            dataIndex: 'positions',
            render: (_, record) =>
              record.positions.map((position) => position.name).join(' / ') ||
              '-',
          },
          {
            title: t('pages.account.dataScope'),
            dataIndex: 'data_scope',
            valueEnum: dataScopeEnum,
          },
          {
            title: t('pages.account.lastLoginAt'),
            dataIndex: 'last_login_at',
            valueType: 'dateTime',
          },
        ]}
      />
      <Divider />
      <Row>
        <Col xs={24} lg={14} xl={10}>
          <ProForm<API.ProfileParams>
            form={form}
            omitNil={false}
            layout="vertical"
            initialValues={initialValues}
            submitter={{
              searchConfig: { submitText: t('pages.account.save') },
              resetButtonProps: false,
            }}
            onFinish={handleFinish}
          >
            <ProForm.Item name="avatar" label={t('pages.account.avatar')}>
              <ImageUpload />
            </ProForm.Item>
            <ProFormText
              name="name"
              label={t('pages.account.name')}
              rules={[{ required: true }, { max: 50 }]}
            />
            <ProFormText
              name="email"
              label={t('pages.account.email')}
              rules={[{ type: 'email' }]}
            />
            <ProFormText name="phone" label={t('pages.account.phone')} />
          </ProForm>
        </Col>
      </Row>
    </>
  );
};

export default BasicSettings;
