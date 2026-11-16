import type { ProFormInstance } from '@ant-design/pro-components';
import {
  ModalForm,
  ProFormDigit,
  ProFormRadio,
  ProFormText,
} from '@ant-design/pro-components';
import { App } from 'antd';
import type React from 'react';
import { useRef } from 'react';
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
  // 不传 form 实例：表单 store 随弹层销毁，每次打开都是干净的
  const formRef = useRef<ProFormInstance<CategoryFormValues>>(undefined);

  const handleFinish = (values: CategoryFormValues) =>
    submitForm(formRef.current, async () => {
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
      formRef={formRef}
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
