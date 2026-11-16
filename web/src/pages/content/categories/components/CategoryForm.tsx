import {
  ModalForm,
  ProFormDigit,
  ProFormRadio,
  ProFormText,
} from '@ant-design/pro-components';
import { App, Form } from 'antd';
import type React from 'react';
import { useStatusEnum } from '@/hooks/useValueEnums';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { Category, CategoryFormValues } from '../data';
import { createCategory, updateCategory } from '../service';

interface CategoryFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  record?: Category;
  onSuccess: () => void;
}

const CategoryForm: React.FC<CategoryFormProps> = ({
  open,
  onOpenChange,
  record,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const statusEnum = useStatusEnum();
  const [form] = Form.useForm<CategoryFormValues>();

  const handleFinish = (values: CategoryFormValues) =>
    submitForm(form, async () => {
      if (record) {
        await updateCategory(record.id, values);
      } else {
        await createCategory(values);
      }
      message.success(t(record ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <ModalForm<CategoryFormValues>
      title={t(
        record
          ? 'pages.content.categories.edit'
          : 'pages.content.categories.create',
      )}
      width={480}
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
        label={t('pages.content.categories.name')}
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
    </ModalForm>
  );
};

export default CategoryForm;
