import type { ProFormInstance } from '@ant-design/pro-components';
import {
  ModalForm,
  ProFormRadio,
  ProFormText,
  ProFormTextArea,
} from '@ant-design/pro-components';
import { App } from 'antd';
import type React from 'react';
import { useRef } from 'react';
import { useStatusEnum } from '@/hooks/useValueEnums';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { DictType, DictTypeFormValues } from '../data';
import { createDictType, updateDictType } from '../service';

interface DictTypeFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  record?: DictType;
  onSuccess: () => void;
}

const DictTypeForm: React.FC<DictTypeFormProps> = ({
  open,
  onOpenChange,
  record,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const statusEnum = useStatusEnum();
  const formRef = useRef<ProFormInstance<DictTypeFormValues>>(undefined);

  const handleFinish = (values: DictTypeFormValues) =>
    submitForm(formRef.current, async () => {
      if (record) {
        await updateDictType(record.id, values);
      } else {
        await createDictType(values);
      }
      message.success(t(record ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <ModalForm<DictTypeFormValues>
      title={t(
        record ? 'pages.system.dicts.edit' : 'pages.system.dicts.create',
      )}
      width={480}
      formRef={formRef}
      omitNil={false}
      open={open}
      onOpenChange={onOpenChange}
      modalProps={{ destroyOnHidden: true }}
      initialValues={record ?? { status: 'active' }}
      onFinish={handleFinish}
    >
      <ProFormText
        name="name"
        label={t('pages.system.dicts.name')}
        rules={[{ required: true }, { max: 50 }]}
      />
      <ProFormText
        name="code"
        label={t('pages.system.dicts.code')}
        tooltip={t('pages.system.dicts.code.tooltip')}
        rules={[
          { required: true },
          { max: 64 },
          { pattern: /^[A-Za-z0-9_-]+$/ },
        ]}
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
        fieldProps={{ rows: 2, maxLength: 255, showCount: true }}
      />
    </ModalForm>
  );
};

export default DictTypeForm;
