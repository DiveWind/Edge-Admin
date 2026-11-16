import {
  ModalForm,
  ProFormDigit,
  ProFormRadio,
  ProFormSelect,
  ProFormText,
  ProFormTreeSelect,
} from '@ant-design/pro-components';
import { App, Form } from 'antd';
import type React from 'react';
import { useStatusEnum } from '@/hooks/useValueEnums';
import {
  getDepartmentOptions,
  getUserOptions,
  toSelectOptions,
} from '@/services/options';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import { disableSubtree } from '@/utils/tree';
import type { Department, DepartmentFormValues } from '../data';
import { createDepartment, updateDepartment } from '../service';

/** 新建（可带预设上级）或编辑 */
export type DepartmentFormTarget =
  | { mode: 'create'; parentId?: API.Id }
  | { mode: 'edit'; record: Department };

interface DepartmentFormProps {
  target?: DepartmentFormTarget;
  onClose: () => void;
  onSuccess: () => void;
}

const DepartmentForm: React.FC<DepartmentFormProps> = ({
  target,
  onClose,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const statusEnum = useStatusEnum();
  const [form] = Form.useForm<DepartmentFormValues>();
  const editing = target?.mode === 'edit' ? target.record : undefined;

  const initialValues: Partial<DepartmentFormValues> = editing
    ? {
        parent_id: editing.parent_id,
        name: editing.name,
        leader_id: editing.leader_id ?? editing.leader?.id ?? null,
        sort: editing.sort,
        status: editing.status,
      }
    : {
        parent_id: target?.mode === 'create' ? target.parentId : undefined,
        sort: 0,
        status: 'active',
      };

  const handleFinish = (values: DepartmentFormValues) =>
    submitForm(form, async () => {
      // 清空下拉框得到 undefined，显式转成 null 才能提交"清空"
      const payload = {
        ...values,
        parent_id: values.parent_id ?? null,
        leader_id: values.leader_id ?? null,
      };
      if (editing) {
        await updateDepartment(editing.id, payload);
      } else {
        await createDepartment(payload);
      }
      message.success(t(editing ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <ModalForm<DepartmentFormValues>
      title={t(
        editing
          ? 'pages.system.departments.edit'
          : 'pages.system.departments.create',
      )}
      width={520}
      form={form}
      omitNil={false}
      open={Boolean(target)}
      onOpenChange={(open) => !open && onClose()}
      modalProps={{ destroyOnHidden: true }}
      initialValues={initialValues}
      onFinish={handleFinish}
    >
      <ProFormTreeSelect
        name="parent_id"
        label={t('pages.system.departments.parent')}
        placeholder={t('pages.system.departments.parent.placeholder')}
        allowClear
        request={async () => {
          const { data } = await getDepartmentOptions();
          // 不能把自己或自己的下级设为上级
          return editing ? disableSubtree(data, editing.id) : data;
        }}
        fieldProps={{
          fieldNames: { label: 'name', value: 'id', children: 'children' },
          showSearch: { treeNodeFilterProp: 'name' },
          treeDefaultExpandAll: true,
        }}
      />
      <ProFormText
        name="name"
        label={t('pages.system.departments.name')}
        rules={[{ required: true }, { max: 50 }]}
      />
      <ProFormSelect
        name="leader_id"
        label={t('pages.system.departments.leader')}
        allowClear
        request={() => getUserOptions().then(toSelectOptions)}
        fieldProps={{ showSearch: { optionFilterProp: 'label' } }}
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

export default DepartmentForm;
