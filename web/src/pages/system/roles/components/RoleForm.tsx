import {
  DrawerForm,
  ProForm,
  ProFormDependency,
  ProFormDigit,
  ProFormRadio,
  ProFormSelect,
  ProFormText,
  ProFormTextArea,
} from '@ant-design/pro-components';
import { useQuery } from '@tanstack/react-query';
import { App, Form } from 'antd';
import type React from 'react';
import { useDataScopeEnum, useStatusEnum } from '@/hooks/useValueEnums';
import { getDepartmentOptions } from '@/services/options';
import { getPermissionTree } from '@/services/permission';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { Role, RoleDetail, RoleFormValues } from '../data';
import { createRole, getRole, SUPER_ADMIN_CODE, updateRole } from '../service';
import CheckTree from './CheckTree';

interface RoleFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  record?: Role;
  onSuccess: () => void;
}

const toFormValues = (role: RoleDetail): RoleFormValues => ({
  name: role.name,
  code: role.code,
  data_scope: role.data_scope,
  sort: role.sort,
  status: role.status,
  remark: role.remark,
  permission_ids: role.permission_ids,
  department_ids: role.department_ids,
});

const RoleForm: React.FC<RoleFormProps> = ({
  open,
  onOpenChange,
  record,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const statusEnum = useStatusEnum();
  const dataScopeEnum = useDataScopeEnum();
  const [form] = Form.useForm<RoleFormValues>();
  const isSuperAdmin = record?.code === SUPER_ADMIN_CODE;

  const { data: permissions = [] } = useQuery({
    queryKey: ['permissions', 'tree'],
    queryFn: async () => (await getPermissionTree()).data,
    enabled: open,
  });
  const { data: departments = [] } = useQuery({
    queryKey: ['options', 'departments'],
    queryFn: async () => (await getDepartmentOptions()).data,
    enabled: open,
  });

  const handleFinish = (values: RoleFormValues) =>
    submitForm(form, async () => {
      const payload: RoleFormValues = {
        ...values,
        department_ids:
          values.data_scope === 'custom' ? values.department_ids : [],
      };
      if (record) {
        await updateRole(record.id, payload);
      } else {
        await createRole(payload);
      }
      message.success(t(record ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <DrawerForm<RoleFormValues>
      title={t(
        record ? 'pages.system.roles.edit' : 'pages.system.roles.create',
      )}
      width={560}
      form={form}
      omitNil={false}
      open={open}
      onOpenChange={onOpenChange}
      drawerProps={{ destroyOnHidden: true }}
      initialValues={{
        data_scope: 'self',
        sort: 0,
        status: 'active',
        permission_ids: [],
        department_ids: [],
      }}
      request={
        record
          ? async () => toFormValues((await getRole(record.id)).data)
          : undefined
      }
      onFinish={handleFinish}
    >
      <ProFormText
        name="name"
        label={t('pages.system.roles.name')}
        rules={[{ required: true }, { max: 50 }]}
      />
      <ProFormText
        name="code"
        label={t('pages.system.roles.code')}
        disabled={isSuperAdmin}
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
        disabled={isSuperAdmin}
        rules={[{ required: true }]}
      />
      <ProFormTextArea
        name="remark"
        label={t('common.remark')}
        fieldProps={{ maxLength: 255, showCount: true }}
      />
      <ProForm.Item
        name="permission_ids"
        label={t('pages.system.roles.permissions')}
      >
        <CheckTree treeData={permissions} disabled={isSuperAdmin} />
      </ProForm.Item>
      <ProFormSelect
        name="data_scope"
        label={t('pages.system.roles.dataScope')}
        valueEnum={dataScopeEnum}
        disabled={isSuperAdmin}
        rules={[{ required: true }]}
      />
      <ProFormDependency name={['data_scope']}>
        {({ data_scope }) =>
          data_scope === 'custom' && (
            <ProForm.Item
              name="department_ids"
              label={t('pages.system.roles.departments')}
            >
              <CheckTree treeData={departments} strict />
            </ProForm.Item>
          )
        }
      </ProFormDependency>
    </DrawerForm>
  );
};

export default RoleForm;
