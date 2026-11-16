import { App, Popconfirm, Typography } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';

export interface DeleteActionProps {
  /** 执行删除，reject 时不提示成功（错误提示由全局 errorHandler 负责） */
  onConfirm: () => Promise<unknown>;
  /** 删除成功后回调，通常是刷新表格 */
  onSuccess?: () => void;
  title?: React.ReactNode;
  /** 成功提示，默认"删除成功" */
  successMessage?: string;
  disabled?: boolean;
  children?: React.ReactNode;
}

/** 表格操作列的"删除"：Popconfirm 二次确认 + 成功提示 */
const DeleteAction: React.FC<DeleteActionProps> = ({
  onConfirm,
  onSuccess,
  title,
  successMessage,
  disabled,
  children,
}) => {
  const t = useT();
  const { message } = App.useApp();

  const handleConfirm = async () => {
    await onConfirm();
    message.success(successMessage ?? t('common.deleted'));
    onSuccess?.();
  };

  return (
    <Popconfirm
      title={title ?? t('common.deleteConfirm')}
      disabled={disabled}
      onConfirm={() => handleConfirm().catch(() => undefined)}
    >
      <Typography.Link type="danger" disabled={disabled}>
        {children ?? t('common.delete')}
      </Typography.Link>
    </Popconfirm>
  );
};

export default DeleteAction;
