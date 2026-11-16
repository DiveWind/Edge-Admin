import { PlusOutlined } from '@ant-design/icons';
import type { UploadFile, UploadProps } from 'antd';
import { App, Flex, Upload } from 'antd';
import type React from 'react';
import { useState } from 'react';
import { uploadFile } from '@/services/upload';
import { useT } from '@/utils/i18n';

const MAX_SIZE_MB = 2;

export interface ImageUploadProps {
  /** 图片 url（`POST /api/uploads` 返回的 url） */
  value?: string | null;
  onChange?: (value: string | null) => void;
  disabled?: boolean;
}

/**
 * 单图上传表单控件，可直接放进 `ProForm.Item` / `Form.Item`。
 * 通过请求层上传（自动带 Bearer Token、统一错误提示）。
 */
const ImageUpload: React.FC<ImageUploadProps> = ({
  value,
  onChange,
  disabled,
}) => {
  const t = useT();
  const { message } = App.useApp();
  // 上传中的文件（展示进度）；完成后以 value 为准
  const [pending, setPending] = useState<UploadFile>();

  const fileList: UploadFile[] = value
    ? [{ uid: value, name: value, status: 'done', url: value }]
    : pending
      ? [pending]
      : [];

  const beforeUpload: UploadProps['beforeUpload'] = (file) => {
    // 后端只接受位图（不含 svg）
    if (!file.type.startsWith('image/') || file.type === 'image/svg+xml') {
      message.error(t('component.upload.imageOnly'));
      return Upload.LIST_IGNORE;
    }
    if (file.size > MAX_SIZE_MB * 1024 * 1024) {
      message.error(t('component.upload.tooLarge', { size: MAX_SIZE_MB }));
      return Upload.LIST_IGNORE;
    }
    return true;
  };

  const customRequest: UploadProps['customRequest'] = async ({
    file,
    onSuccess,
    onError,
  }) => {
    try {
      const { data } = await uploadFile(file as Blob);
      onSuccess?.(data);
      onChange?.(data.url);
    } catch (error) {
      onError?.(error as Error);
    }
  };

  return (
    <Upload
      accept="image/png,image/jpeg,image/gif,image/webp"
      listType="picture-card"
      maxCount={1}
      disabled={disabled}
      fileList={fileList}
      beforeUpload={beforeUpload}
      customRequest={customRequest}
      onChange={({ file }) =>
        setPending(file.status === 'uploading' ? file : undefined)
      }
      onRemove={() => onChange?.(null)}
    >
      {fileList.length === 0 && (
        <Flex vertical align="center" gap={4}>
          <PlusOutlined />
          {t('component.upload.select')}
        </Flex>
      )}
    </Upload>
  );
};

export default ImageUpload;
