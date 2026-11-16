import type { ProFormInstance } from '@ant-design/pro-components';
import {
  ModalForm,
  ProFormRadio,
  ProFormText,
  ProFormTextArea,
} from '@ant-design/pro-components';
import { App } from 'antd';
import type React from 'react';
import { useRef } from 'react';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { Announcement, AnnouncementFormValues } from '../data';
import { createAnnouncement, updateAnnouncement } from '../service';
import useAnnouncementTypeEnum from '../useAnnouncementTypeEnum';

interface AnnouncementFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  record?: Announcement;
  onSuccess: () => void;
}

/** 新建 / 编辑；已发布的公告不可编辑，以只读方式查看 */
const AnnouncementForm: React.FC<AnnouncementFormProps> = ({
  open,
  onOpenChange,
  record,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const typeEnum = useAnnouncementTypeEnum();
  // 不传 form 实例：表单 store 随弹层销毁，每次打开都是干净的
  const formRef = useRef<ProFormInstance<AnnouncementFormValues>>(undefined);
  const readonly = record?.status === 'published';

  const title = readonly
    ? 'pages.message.announcements.view'
    : record
      ? 'pages.message.announcements.edit'
      : 'pages.message.announcements.create';

  const handleFinish = (values: AnnouncementFormValues) =>
    submitForm(formRef.current, async () => {
      if (record) {
        await updateAnnouncement(record.id, values);
      } else {
        await createAnnouncement(values);
      }
      message.success(t(record ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <ModalForm<AnnouncementFormValues>
      title={t(title)}
      width={640}
      formRef={formRef}
      omitNil={false}
      open={open}
      onOpenChange={onOpenChange}
      readonly={readonly}
      submitter={readonly ? false : undefined}
      modalProps={{ destroyOnHidden: true }}
      initialValues={record ?? { type: 'notice' }}
      onFinish={handleFinish}
    >
      <ProFormText
        name="title"
        label={t('pages.message.announcements.title')}
        rules={[{ required: true }, { max: 100 }]}
      />
      <ProFormRadio.Group
        name="type"
        label={t('pages.message.announcements.type')}
        valueEnum={typeEnum}
        rules={[{ required: true }]}
      />
      <ProFormTextArea
        name="content"
        label={t('pages.message.announcements.content')}
        fieldProps={{ rows: 8 }}
        rules={[{ required: true }]}
      />
    </ModalForm>
  );
};

export default AnnouncementForm;
