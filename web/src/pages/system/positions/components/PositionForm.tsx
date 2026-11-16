import {
  ModalForm,
  ProFormDigit,
  ProFormRadio,
  ProFormText,
  ProFormTextArea,
} from '@ant-design/pro-components';
import { App, Form } from 'antd';
import type React from 'react';
import { useStatusEnum } from '@/hooks/useValueEnums';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { Position, PositionFormValues } from '../data';
import { createPosition, updatePosition } from '../service';

interface PositionFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  record?: Position;
  onSuccess: () => void;
}

const PositionForm: React.FC<PositionFormProps> = ({
  open,
  onOpenChange,
  record,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const statusEnum = useStatusEnum();
  const [form] = Form.useForm<PositionFormValues>();

  const handleFinish = (values: PositionFormValues) =>
    submitForm(form, async () => {
      if (record) {
        await updatePosition(record.id, values);
      } else {
        await createPosition(values);
      }
      message.success(t(record ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <ModalForm<PositionFormValues>
      title={t(
        record
          ? 'pages.system.positions.edit'
          : 'pages.system.positions.create',
      )}
      width={520}
      form={form}
      omitNil={false}
      open={open}
      onOpenChange={onOpenChange}
      modalProps={{ destroyOnHidden: true }}
      initialValues={record ?? { sort: 0, status: 'active' }}
      onFinish={handleFinish}
    >
      <ProFormText
        name="name"
        label={t('pages.system.positions.name')}
        rules={[{ required: true }, { max: 50 }]}
      />
      <ProFormText
        name="code"
        label={t('pages.system.positions.code')}
        rules={[{ required: true }, { max: 50 }]}
      />
      <ProFormDigit
        name="sort"
        label={t('common.sort')}
        min={0}
        fieldProps={{ precision: 0 }}
      />
      <ProFormRadio.Group
        name="status"
        label={t('common.status')}
        valueEnum={statusEnum}
        rules={[{ required: true }]}
      />
      <ProFormTextArea
        name="remark"
        label={t('common.remark')}
        fieldProps={{ maxLength: 255, showCount: true }}
      />
    </ModalForm>
  );
};

export default PositionForm;
