import { ModalForm, ProFormText } from '@ant-design/pro-components';
import { App, Form } from 'antd';
import type React from 'react';
import { sameAsRule, submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { ResetPasswordValues, User } from '../data';
import { resetUserPassword } from '../service';

interface ResetPasswordFormProps {
  record?: User;
  onClose: () => void;
}

const ResetPasswordForm: React.FC<ResetPasswordFormProps> = ({
  record,
  onClose,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const [form] = Form.useForm<ResetPasswordValues>();

  return (
    <ModalForm<ResetPasswordValues>
      title={t('pages.system.users.resetPassword.title', {
        name: record?.name ?? '',
      })}
      width={420}
      form={form}
      omitNil={false}
      open={Boolean(record)}
      onOpenChange={(open) => !open && onClose()}
      modalProps={{ destroyOnHidden: true }}
      onFinish={(values) =>
        submitForm(form, async () => {
          if (!record) return;
          await resetUserPassword(record.id, values);
          message.success(t('pages.system.users.resetPassword.success'));
        })
      }
    >
      <ProFormText.Password
        name="password"
        label={t('pages.system.users.resetPassword.password')}
        rules={[{ required: true }, { min: 8 }]}
      />
      <ProFormText.Password
        name="password_confirmation"
        label={t('pages.system.users.resetPassword.confirm')}
        dependencies={['password']}
        rules={[
          { required: true },
          sameAsRule('password', t('common.passwordMismatch')),
        ]}
      />
    </ModalForm>
  );
};

export default ResetPasswordForm;
