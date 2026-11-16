import type { ProFormInstance } from '@ant-design/pro-components';
import {
  ModalForm,
  ProForm,
  ProFormRadio,
  ProFormSelect,
  ProFormText,
  ProFormTreeSelect,
} from '@ant-design/pro-components';
import { App, Col } from 'antd';
import type React from 'react';
import { useRef } from 'react';
import { ImageUpload } from '@/components';
import { useStatusEnum } from '@/hooks/useValueEnums';
import {
  getPositionOptions,
  getRoleOptions,
  toSelectOptions,
} from '@/services/options';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { User, UserFormValues } from '../data';
import { createUser, getUser, toUserFormValues, updateUser } from '../service';

interface UserFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** 有值为编辑，否则为新建 */
  record?: User;
  departments: API.DepartmentOption[];
  onSuccess: () => void;
}

const UserForm: React.FC<UserFormProps> = ({
  open,
  onOpenChange,
  record,
  departments,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const statusEnum = useStatusEnum();
  // 不传 form 实例：表单 store 随弹层销毁，每次打开都是干净的
  const formRef = useRef<ProFormInstance<UserFormValues>>(undefined);
  const isEdit = Boolean(record);

  const handleFinish = (values: UserFormValues) =>
    submitForm(formRef.current, async () => {
      // 清空下拉框得到 undefined，显式转成 null 才能提交"清空"
      const payload = {
        ...values,
        department_id: values.department_id ?? null,
      };
      if (record) {
        await updateUser(record.id, payload);
      } else {
        await createUser(payload);
      }
      message.success(t(isEdit ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <ModalForm<UserFormValues>
      title={t(
        isEdit ? 'pages.system.users.edit' : 'pages.system.users.create',
      )}
      formRef={formRef}
      omitNil={false}
      open={open}
      onOpenChange={onOpenChange}
      modalProps={{ destroyOnHidden: true }}
      grid
      rowProps={{ gutter: 16 }}
      initialValues={{ status: 'active', role_ids: [], position_ids: [] }}
      request={
        record
          ? async () => toUserFormValues((await getUser(record.id)).data)
          : undefined
      }
      onFinish={handleFinish}
    >
      <ProFormText
        name="username"
        label={t('pages.system.users.username')}
        colProps={{ span: 12 }}
        disabled={isEdit}
        rules={[{ required: true }, { max: 50 }]}
      />
      <ProFormText
        name="name"
        label={t('pages.system.users.name')}
        colProps={{ span: 12 }}
        rules={[{ required: true }, { max: 50 }]}
      />
      {!isEdit && (
        <ProFormText.Password
          name="password"
          label={t('pages.system.users.password')}
          colProps={{ span: 24 }}
          rules={[{ required: true }, { min: 8 }]}
        />
      )}
      <ProFormText
        name="email"
        label={t('pages.system.users.email')}
        colProps={{ span: 12 }}
        rules={[{ type: 'email' }]}
      />
      <ProFormText
        name="phone"
        label={t('pages.system.users.phone')}
        colProps={{ span: 12 }}
      />
      <ProFormTreeSelect
        name="department_id"
        label={t('pages.system.users.department')}
        colProps={{ span: 12 }}
        allowClear
        fieldProps={{
          treeData: departments,
          fieldNames: { label: 'name', value: 'id', children: 'children' },
          showSearch: { treeNodeFilterProp: 'name' },
          treeDefaultExpandAll: true,
        }}
      />
      <ProFormRadio.Group
        name="status"
        label={t('common.status')}
        colProps={{ span: 12 }}
        valueEnum={statusEnum}
        rules={[{ required: true }]}
      />
      <ProFormSelect
        name="role_ids"
        label={t('pages.system.users.roles')}
        colProps={{ span: 12 }}
        mode="multiple"
        request={() => getRoleOptions().then(toSelectOptions)}
        fieldProps={{ showSearch: { optionFilterProp: 'label' } }}
      />
      <ProFormSelect
        name="position_ids"
        label={t('pages.system.users.positions')}
        colProps={{ span: 12 }}
        mode="multiple"
        request={() => getPositionOptions().then(toSelectOptions)}
        fieldProps={{ showSearch: { optionFilterProp: 'label' } }}
      />
      {/* ProForm.Item 不参与 grid，需要手动包一层 Col */}
      <Col span={24}>
        <ProForm.Item name="avatar" label={t('pages.system.users.avatar')}>
          <ImageUpload />
        </ProForm.Item>
      </Col>
    </ModalForm>
  );
};

export default UserForm;
