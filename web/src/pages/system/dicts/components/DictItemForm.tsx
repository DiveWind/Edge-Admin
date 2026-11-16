import type { ProFormInstance } from '@ant-design/pro-components';
import {
  ModalForm,
  ProForm,
  ProFormDependency,
  ProFormDigit,
  ProFormRadio,
  ProFormSelect,
  ProFormText,
  ProFormTextArea,
} from '@ant-design/pro-components';
import { App, Tag } from 'antd';
import type React from 'react';
import { useRef } from 'react';
import { useStatusEnum } from '@/hooks/useValueEnums';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { DictItem, DictItemFormValues } from '../data';
import { createDictItem, updateDictItem } from '../service';

/** 语义色（映射为 Badge 状态）+ antd 预设色 */
const COLORS = [
  'success',
  'processing',
  'error',
  'warning',
  'default',
  'magenta',
  'red',
  'volcano',
  'orange',
  'gold',
  'lime',
  'green',
  'cyan',
  'blue',
  'geekblue',
  'purple',
];

interface DictItemFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  dictTypeId: API.Id;
  record?: DictItem;
  onSuccess: () => void;
}

const DictItemForm: React.FC<DictItemFormProps> = ({
  open,
  onOpenChange,
  dictTypeId,
  record,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const statusEnum = useStatusEnum();
  const formRef = useRef<ProFormInstance<DictItemFormValues>>(undefined);

  const handleFinish = (values: DictItemFormValues) =>
    submitForm(formRef.current, async () => {
      const payload = {
        ...values,
        dict_type_id: dictTypeId,
        color: values.color ?? null,
      };
      if (record) {
        await updateDictItem(record.id, payload);
      } else {
        await createDictItem(payload);
      }
      message.success(t(record ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <ModalForm<DictItemFormValues>
      title={t(
        record
          ? 'pages.system.dicts.item.edit'
          : 'pages.system.dicts.item.create',
      )}
      width={520}
      formRef={formRef}
      omitNil={false}
      open={open}
      onOpenChange={onOpenChange}
      modalProps={{ destroyOnHidden: true }}
      initialValues={record ?? { sort: 0, status: 'active' }}
      onFinish={handleFinish}
    >
      <ProForm.Group>
        <ProFormText
          name="label"
          label={t('pages.system.dicts.item.label')}
          width="sm"
          rules={[{ required: true }, { max: 100 }]}
        />
        <ProFormText
          name="value"
          label={t('pages.system.dicts.item.value')}
          tooltip={t('pages.system.dicts.item.value.tooltip')}
          width="sm"
          rules={[{ required: true }, { max: 64 }]}
        />
      </ProForm.Group>
      <ProForm.Group>
        <ProFormSelect
          name="color"
          label={t('pages.system.dicts.item.color')}
          width="sm"
          placeholder={t('pages.system.dicts.item.color.placeholder')}
          options={COLORS.map((color) => ({
            value: color,
            label: <Tag color={color}>{color}</Tag>,
          }))}
        />
        <ProFormDependency name={['label', 'color']}>
          {({ label, color }: Partial<DictItemFormValues>) => (
            <ProForm.Item label={t('pages.system.dicts.item.preview')}>
              <Tag color={color || undefined}>{label || '-'}</Tag>
            </ProForm.Item>
          )}
        </ProFormDependency>
      </ProForm.Group>
      <ProForm.Group>
        <ProFormDigit
          name="sort"
          label={t('common.sort')}
          width="sm"
          min={0}
          fieldProps={{ precision: 0 }}
        />
        <ProFormRadio.Group
          name="status"
          label={t('common.status')}
          valueEnum={statusEnum}
          rules={[{ required: true }]}
        />
      </ProForm.Group>
      <ProFormTextArea
        name="remark"
        label={t('common.remark')}
        fieldProps={{ rows: 2, maxLength: 255, showCount: true }}
      />
    </ModalForm>
  );
};

export default DictItemForm;
