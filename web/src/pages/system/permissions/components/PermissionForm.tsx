import type { ProFormInstance } from '@ant-design/pro-components';
import {
  ModalForm,
  ProForm,
  ProFormDependency,
  ProFormDigit,
  ProFormRadio,
  ProFormSwitch,
  ProFormText,
  ProFormTextArea,
  ProFormTreeSelect,
} from '@ant-design/pro-components';
import { useQuery } from '@tanstack/react-query';
import { App } from 'antd';
import type React from 'react';
import { useMemo, useRef } from 'react';
import { IconPicker } from '@/components';
import { usePermissionTranslator } from '@/hooks/usePermissionName';
import { useStatusEnum } from '@/hooks/useValueEnums';
import { clearFieldErrors, submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { Permission, PermissionFormValues } from '../data';
import {
  createPermission,
  queryPermissions,
  toPayload,
  updatePermission,
} from '../service';
import {
  allowedChildTypes,
  defaultChildType,
  findNode,
  parentOptions,
} from '../tree';

/** 新建（可在某行"添加下级"预设上级）或编辑 */
export type PermissionFormTarget =
  | { mode: 'create'; parent?: Permission }
  | { mode: 'edit'; record: Permission };

interface PermissionFormProps {
  target?: PermissionFormTarget;
  onClose: () => void;
  onSuccess: () => void;
}

const TYPES: API.PermissionType[] = ['directory', 'menu', 'action'];

/**
 * 按类型联动字段：目录（名称 / 图标 / 排序）、菜单（+ 路由 / 外链）、按钮（名称 / 权限码）。
 * 内置节点的权限码 / 类型 / 路由只读。
 */
const PermissionForm: React.FC<PermissionFormProps> = ({
  target,
  onClose,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const statusEnum = useStatusEnum();
  const translate = usePermissionTranslator();
  // 不传 form 实例：表单 store 随弹层销毁，每次打开都是干净的
  const formRef = useRef<ProFormInstance<PermissionFormValues>>(undefined);
  const editing = target?.mode === 'edit' ? target.record : undefined;
  const builtin = Boolean(editing?.is_system);

  const { data: rawTree } = useQuery({
    queryKey: ['permissions', 'tree'],
    queryFn: async () => (await queryPermissions()).data,
    enabled: Boolean(target),
  });
  const tree = useMemo(() => translate(rawTree ?? []), [rawTree, translate]);

  const initialValues: Partial<PermissionFormValues> = editing
    ? {
        type: editing.type,
        parent_id: editing.parent_id,
        name: editing.name,
        code: editing.code,
        path: editing.path,
        icon: editing.icon,
        is_external: editing.is_external,
        is_visible: editing.is_visible,
        status: editing.status,
        sort: editing.sort,
        remark: editing.remark,
      }
    : {
        type: defaultChildType(
          target?.mode === 'create' ? (target.parent?.type ?? null) : null,
        ),
        parent_id: target?.mode === 'create' ? target.parent?.id : undefined,
        // 在菜单下添加按钮时预填权限码前缀
        code:
          target?.mode === 'create' && target.parent?.type === 'menu'
            ? `${target.parent.code}.`
            : undefined,
        is_external: false,
        is_visible: true,
        status: 'active',
        sort: 0,
      };

  const typeOptions = (parentId?: API.Id | null) => {
    const parent = findNode(rawTree ?? [], parentId);
    const allowed = allowedChildTypes(parent?.type ?? null);
    return TYPES.map((type) => ({
      label: t(`component.permissionType.${type}`),
      value: type,
      disabled: builtin || !allowed.includes(type),
    }));
  };

  const handleFinish = (values: PermissionFormValues) =>
    submitForm(formRef.current, async () => {
      const payload = toPayload(values);
      if (editing) {
        await updatePermission(editing.id, payload);
      } else {
        await createPermission(payload);
      }
      message.success(t(editing ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <ModalForm<PermissionFormValues>
      title={t(
        editing
          ? 'pages.system.permissions.edit'
          : 'pages.system.permissions.create',
      )}
      width={560}
      formRef={formRef}
      omitNil={false}
      open={Boolean(target)}
      onOpenChange={(open) => !open && onClose()}
      modalProps={{ destroyOnHidden: true }}
      initialValues={initialValues}
      onValuesChange={(changed) => clearFieldErrors(formRef.current, changed)}
      onFinish={handleFinish}
    >
      <ProFormDependency name={['parent_id']}>
        {({ parent_id }) => (
          <ProFormRadio.Group
            name="type"
            label={t('pages.system.permissions.type')}
            radioType="button"
            options={typeOptions(parent_id)}
            rules={[{ required: true }]}
          />
        )}
      </ProFormDependency>
      <ProFormDependency name={['type']}>
        {({ type }) => (
          <ProFormTreeSelect
            name="parent_id"
            label={t('pages.system.permissions.parent')}
            placeholder={t('pages.system.permissions.parent.placeholder')}
            allowClear
            rules={type === 'action' ? [{ required: true }] : undefined}
            fieldProps={{
              treeData: parentOptions(tree, type ?? 'menu', editing?.id),
              fieldNames: { label: 'name', value: 'id', children: 'children' },
              showSearch: { treeNodeFilterProp: 'name' },
              treeDefaultExpandAll: true,
            }}
          />
        )}
      </ProFormDependency>
      <ProFormText
        name="name"
        label={t('pages.system.permissions.name')}
        rules={[{ required: true }, { max: 64 }]}
      />
      <ProFormText
        name="code"
        label={t('pages.system.permissions.code')}
        tooltip={t('pages.system.permissions.code.tooltip')}
        disabled={builtin}
        rules={[{ required: true }, { max: 128 }]}
      />
      <ProFormDependency name={['type', 'is_external']}>
        {({ type, is_external }) => (
          <>
            {type === 'menu' && (
              <>
                <ProFormSwitch
                  name="is_external"
                  label={t('pages.system.permissions.isExternal')}
                  tooltip={t('pages.system.permissions.isExternal.tooltip')}
                  disabled={builtin}
                />
                <ProFormText
                  name="path"
                  label={t('pages.system.permissions.path')}
                  tooltip={t('pages.system.permissions.path.tooltip')}
                  placeholder={t(
                    is_external
                      ? 'pages.system.permissions.externalPath.placeholder'
                      : 'pages.system.permissions.path.placeholder',
                  )}
                  disabled={builtin}
                  rules={[{ required: true }, { max: 255 }]}
                />
              </>
            )}
            {type !== 'action' && (
              <ProForm.Item
                name="icon"
                label={t('pages.system.permissions.icon')}
                tooltip={t('pages.system.permissions.icon.tooltip')}
              >
                <IconPicker />
              </ProForm.Item>
            )}
          </>
        )}
      </ProFormDependency>
      <ProFormDigit
        name="sort"
        label={t('common.sort')}
        min={0}
        fieldProps={{ precision: 0 }}
      />
      <ProFormDependency name={['type']}>
        {({ type }) =>
          type !== 'action' && (
            <ProFormSwitch
              name="is_visible"
              label={t('pages.system.permissions.isVisible')}
              tooltip={t('pages.system.permissions.isVisible.tooltip')}
            />
          )
        }
      </ProFormDependency>
      <ProFormRadio.Group
        name="status"
        label={t('common.status')}
        tooltip={t('pages.system.permissions.status.tooltip')}
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

export default PermissionForm;
