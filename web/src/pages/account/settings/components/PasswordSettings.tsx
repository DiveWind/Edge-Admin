import { ProForm, ProFormText } from '@ant-design/pro-components';
import { App, Col, Form, Row } from 'antd';
import type React from 'react';
import { updatePassword } from '@/services/auth';
import { sameAsRule, submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';

/** 修改密码（`PUT /api/auth/password`） */
const PasswordSettings: React.FC = () => {
  const t = useT();
  const { message } = App.useApp();
  const [form] = Form.useForm<API.PasswordParams>();

  const handleFinish = (values: API.PasswordParams) =>
    submitForm(form, async () => {
      await updatePassword(values);
      form.resetFields();
      message.success(t('pages.account.password.success'));
    });

  return (
    <Row>
      <Col xs={24} lg={14} xl={10}>
        <ProForm<API.PasswordParams>
          form={form}
          layout="vertical"
          submitter={{
            searchConfig: { submitText: t('pages.account.password.submit') },
            resetButtonProps: false,
          }}
          onFinish={handleFinish}
        >
          <ProFormText.Password
            name="current_password"
            label={t('pages.account.password.current')}
            rules={[{ required: true }]}
          />
          <ProFormText.Password
            name="password"
            label={t('pages.account.password.new')}
            rules={[{ required: true }, { min: 8 }]}
          />
          <ProFormText.Password
            name="password_confirmation"
            label={t('pages.account.password.confirm')}
            dependencies={['password']}
            rules={[
              { required: true },
              sameAsRule('password', t('common.passwordMismatch')),
            ]}
          />
        </ProForm>
      </Col>
    </Row>
  );
};

export default PasswordSettings;
