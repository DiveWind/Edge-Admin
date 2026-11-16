import type { ProFormInstance } from '@ant-design/pro-components';
import { ModalForm, ProFormText } from '@ant-design/pro-components';
import { App } from 'antd';
import type React from 'react';
import { useRef } from 'react';
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
  // 不传 form 实例：表单 store 随弹层销毁，每次打开都是干净的
  const formRef = useRef<ProFormInstance<ResetPasswordValues>>(undefined);

  return (
    <ModalForm<ResetPasswordValues>
      title={t('pages.system.users.resetPassword.title', {
        name: record?.name ?? '',
      })}
      width={420}
      formRef={formRef}
      omitNil={false}
      open={Boolean(record)}
      onOpenChange={(open) => !open && onClose()}
      modalProps={{ destroyOnHidden: true }}
      onFinish={(values) =>
        submitForm(formRef.current, async () => {
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
