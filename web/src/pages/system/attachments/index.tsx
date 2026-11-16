import { UploadOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import type { UploadProps } from 'antd';
import { App, Button, Image, Typography, Upload } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import { getUserOptions, toSelectOptions } from '@/services/options';
import { uploadFile } from '@/services/upload';
import { formatBytes } from '@/utils/format';
import { useT } from '@/utils/i18n';
import { createdRangeTransform, tableRequest } from '@/utils/table';
import FileIcon from './components/FileIcon';
import { deleteAttachment, queryAttachments } from './service';

const isImage = (mime: string) =>
  mime.startsWith('image/') && mime !== 'image/svg+xml';

/** 附件：上传接口自动登记；删除会同时删除物理文件 */
const AttachmentList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const { message } = App.useApp();
  const actionRef = useRef<ActionType>(null);
  const [uploading, setUploading] = useState(false);

  const reload = () => actionRef.current?.reload();

  const customRequest: UploadProps['customRequest'] = async ({
    file,
    onSuccess,
    onError,
  }) => {
    setUploading(true);
    try {
      const { data } = await uploadFile(file as Blob);
      onSuccess?.(data);
      message.success(t('pages.system.attachments.uploaded'));
      reload();
    } catch (error) {
      onError?.(error as Error);
    } finally {
      setUploading(false);
    }
  };

  const columns: ProColumns<API.Attachment>[] = [
    {
      title: t('pages.system.attachments.preview'),
      dataIndex: 'url',
      search: false,
      width: 80,
      render: (_, record) =>
        isImage(record.mime) ? (
          <Image
            src={record.url}
            alt={record.name}
            width={48}
            height={48}
            style={{ objectFit: 'cover' }}
          />
        ) : (
          <FileIcon mime={record.mime} />
        ),
    },
    {
      title: t('pages.system.attachments.name'),
      dataIndex: 'name',
      ellipsis: true,
      width: 240,
    },
    {
      title: t('pages.system.attachments.mime'),
      dataIndex: 'mime',
      valueType: 'select',
      // mime 为模糊匹配：按大类筛选
      valueEnum: {
        'image/': { text: t('pages.system.attachments.mime.image') },
        pdf: { text: t('pages.system.attachments.mime.pdf') },
        word: { text: t('pages.system.attachments.mime.word') },
        sheet: { text: t('pages.system.attachments.mime.sheet') },
        presentation: {
          text: t('pages.system.attachments.mime.presentation'),
        },
        'text/': { text: t('pages.system.attachments.mime.text') },
        zip: { text: t('pages.system.attachments.mime.zip') },
      },
      render: (_, record) => (
        <Typography.Text type="secondary">{record.mime}</Typography.Text>
      ),
    },
    {
      title: t('pages.system.attachments.size'),
      dataIndex: 'size',
      search: false,
      sorter: true,
      width: 100,
      renderText: (size: number) => formatBytes(size),
    },
    {
      title: t('pages.system.attachments.uploader'),
      dataIndex: 'uploaded_by',
      valueType: 'select',
      width: 120,
      request: () => getUserOptions().then(toSelectOptions),
      fieldProps: { showSearch: { optionFilterProp: 'label' } },
      render: (_, record) => record.uploader?.name ?? '-',
    },
    {
      title: t('pages.system.attachments.uploadedAt'),
      dataIndex: 'created_at',
      valueType: 'dateTime',
      width: 170,
      search: false,
      sorter: true,
    },
    {
      title: t('pages.system.attachments.uploadedAt'),
      dataIndex: 'created_range',
      valueType: 'dateRange',
      hideInTable: true,
      search: { transform: createdRangeTransform },
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 180,
      render: (_, record) => [
        <Typography.Link
          key="open"
          href={record.url}
          target="_blank"
          rel="noreferrer"
        >
          {t('pages.system.attachments.open')}
        </Typography.Link>,
        <Typography.Text
          key="copy"
          copyable={{
            text: record.url,
            icon: [
              <Typography.Link key="copy">
                {t('pages.system.attachments.copyLink')}
              </Typography.Link>,
              <Typography.Text key="copied" type="success">
                {t('pages.system.attachments.copied')}
              </Typography.Text>,
            ],
            tooltips: false,
          }}
        />,
        access.can('system.attachment.delete') && (
          <DeleteAction
            key="delete"
            title={t('pages.system.attachments.deleteConfirm')}
            onConfirm={() => deleteAttachment(record.id)}
            onSuccess={reload}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<API.Attachment>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryAttachments)}
        toolBarRender={() => [
          <Upload
            key="upload"
            showUploadList={false}
            customRequest={customRequest}
          >
            <Button
              type="primary"
              icon={<UploadOutlined />}
              loading={uploading}
            >
              {t('pages.system.attachments.upload')}
            </Button>
          </Upload>,
        ]}
      />
    </PageContainer>
  );
};

export default AttachmentList;
