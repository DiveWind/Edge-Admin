import type { ProFormInstance } from '@ant-design/pro-components';
import {
  ModalForm,
  ProForm,
  ProFormDependency,
  ProFormDigit,
  ProFormSelect,
  ProFormSwitch,
  ProFormText,
  ProFormTextArea,
} from '@ant-design/pro-components';
import { App, AutoComplete } from 'antd';
import type React from 'react';
import { useRef } from 'react';
import {
  clearFieldErrors,
  jsonRule,
  parseJson,
  stringifyJson,
  submitForm,
} from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { Setting, SettingFormValues, SettingType } from '../data';
import { createSetting, updateSetting } from '../service';
import { useSettingTypeEnum } from '../useSettingTypeEnum';

interface SettingFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  record?: Setting;
  groups: string[];
  onSuccess: () => void;
}

/** 表单中 json 以格式化文本编辑，提交时再解析 */
const toFormValues = (setting: Setting): SettingFormValues => ({
  group: setting.group,
  key: setting.key,
  name: setting.name,
  type: setting.type,
  value: setting.type === 'json' ? stringifyJson(setting.value) : setting.value,
  is_public: setting.is_public,
  remark: setting.remark,
});

/** 参数值控件随类型变化 */
const ValueField: React.FC<{ type?: SettingType }> = ({ type }) => {
  const t = useT();
  const label = t('pages.system.settings.value');
  switch (type) {
    case 'number':
      return <ProFormDigit name="value" label={label} width="md" />;
    case 'boolean':
      return <ProFormSwitch name="value" label={label} />;
    case 'json':
      return (
        <ProFormTextArea
          name="value"
          label={label}
          fieldProps={{ rows: 6, style: { fontFamily: 'monospace' } }}
          rules={[jsonRule(t('common.jsonInvalid'))]}
        />
      );
    default:
      return (
        <ProFormTextArea
          name="value"
          label={label}
          fieldProps={{ autoSize: { minRows: 1, maxRows: 6 } }}
        />
      );
  }
};

const SettingForm: React.FC<SettingFormProps> = ({
  open,
  onOpenChange,
  record,
  groups,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const typeEnum = useSettingTypeEnum();
  const formRef = useRef<ProFormInstance<SettingFormValues>>(undefined);
  const isSystem = Boolean(record?.is_system);

  const handleFinish = (values: SettingFormValues) =>
    submitForm(formRef.current, async () => {
      const payload: SettingFormValues = {
        ...values,
        value:
          values.type === 'json'
            ? (parseJson(values.value) as Setting['value'])
            : (values.value ?? null),
        is_public: Boolean(values.is_public),
      };
      if (record) {
        await updateSetting(record.id, payload);
      } else {
        await createSetting(payload);
      }
      message.success(t(record ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <ModalForm<SettingFormValues>
      title={t(
        record ? 'pages.system.settings.edit' : 'pages.system.settings.create',
      )}
      width={560}
      formRef={formRef}
      omitNil={false}
      open={open}
      onOpenChange={onOpenChange}
      modalProps={{ destroyOnHidden: true }}
      initialValues={
        record
          ? toFormValues(record)
          : { type: 'string', is_public: false, value: '' }
      }
      onValuesChange={(changed: Partial<SettingFormValues>) => {
        clearFieldErrors(formRef.current, changed);
        // 切换类型时清空旧值，避免把字符串提交给 number / boolean
        if ('type' in changed) {
          formRef.current?.setFieldValue(
            'value',
            changed.type === 'boolean' ? false : undefined,
          );
        }
      }}
      onFinish={handleFinish}
    >
      <ProForm.Group>
        <ProForm.Item
          name="group"
          label={t('pages.system.settings.group')}
          rules={[{ required: true }, { max: 50 }]}
          style={{ width: 216 }}
        >
          <AutoComplete options={groups.map((group) => ({ value: group }))} />
        </ProForm.Item>
        <ProFormText
          name="name"
          label={t('pages.system.settings.name')}
          width="sm"
          rules={[{ required: true }, { max: 100 }]}
        />
      </ProForm.Group>
      <ProForm.Group>
        <ProFormText
          name="key"
          label={t('pages.system.settings.key')}
          tooltip={
            isSystem
              ? t('pages.system.settings.system.tooltip')
              : t('pages.system.settings.key.tooltip')
          }
          width="sm"
          disabled={isSystem}
          rules={[
            { required: true },
            { max: 100 },
            { pattern: /^[a-z0-9_]+(\.[a-z0-9_]+)*$/ },
          ]}
        />
        <ProFormSelect
          name="type"
          label={t('pages.system.settings.type')}
          width="sm"
          disabled={isSystem}
          allowClear={false}
          valueEnum={typeEnum}
          rules={[{ required: true }]}
        />
      </ProForm.Group>
      <ProFormDependency name={['type']}>
        {({ type }: Partial<SettingFormValues>) => <ValueField type={type} />}
      </ProFormDependency>
      <ProFormSwitch
        name="is_public"
        label={t('pages.system.settings.isPublic')}
        tooltip={t('pages.system.settings.isPublic.tooltip')}
      />
      <ProFormTextArea
        name="remark"
        label={t('common.remark')}
        fieldProps={{ rows: 2, maxLength: 255, showCount: true }}
      />
    </ModalForm>
  );
};

export default SettingForm;
