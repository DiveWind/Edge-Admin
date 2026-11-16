import { PlusOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { App, Button, Popconfirm, Typography } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction } from '@/components';
import { usePublishStatusEnum } from '@/hooks/useValueEnums';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import AnnouncementForm from './components/AnnouncementForm';
import type { Announcement } from './data';
import {
  deleteAnnouncement,
  publishAnnouncement,
  queryAnnouncements,
} from './service';
import useAnnouncementTypeEnum from './useAnnouncementTypeEnum';

const AnnouncementList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const { message } = App.useApp();
  const typeEnum = useAnnouncementTypeEnum();
  const statusEnum = usePublishStatusEnum();
  const actionRef = useRef<ActionType>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Announcement>();

  const reload = () => actionRef.current?.reload();

  const openForm = (record?: Announcement) => {
    setEditing(record);
    setFormOpen(true);
  };

  const handlePublish = async (record: Announcement) => {
    await publishAnnouncement(record.id);
    message.success(t('pages.message.announcements.published'));
    reload();
  };

  const columns: ProColumns<Announcement>[] = [
    {
      title: t('pages.message.announcements.title'),
      dataIndex: 'title',
      ellipsis: true,
    },
    {
      title: t('pages.message.announcements.type'),
      dataIndex: 'type',
      valueType: 'select',
      valueEnum: typeEnum,
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: statusEnum,
    },
    {
      title: t('common.publishedAt'),
      dataIndex: 'published_at',
      valueType: 'dateTime',
      search: false,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'created_at',
      valueType: 'dateTime',
      search: false,
      sorter: true,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 180,
      render: (_, record) => {
        const isDraft = record.status === 'draft';
        return [
          isDraft ? (
            access.can('message.announcement.update') && (
              <Typography.Link key="edit" onClick={() => openForm(record)}>
                {t('common.edit')}
              </Typography.Link>
            )
          ) : (
            <Typography.Link key="view" onClick={() => openForm(record)}>
              {t('common.detail')}
            </Typography.Link>
          ),
          isDraft && access.can('message.announcement.publish') && (
            <Popconfirm
              key="publish"
              title={t('pages.message.announcements.publishConfirm')}
              onConfirm={() => handlePublish(record).catch(() => undefined)}
            >
              <Typography.Link>
                {t('pages.message.announcements.publish')}
              </Typography.Link>
            </Popconfirm>
          ),
          access.can('message.announcement.delete') && (
            <DeleteAction
              key="delete"
              onConfirm={() => deleteAnnouncement(record.id)}
              onSuccess={reload}
            />
          ),
        ];
      },
    },
  ];

  return (
    <PageContainer>
      <ProTable<Announcement>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryAnnouncements)}
        toolBarRender={() => [
          access.can('message.announcement.create') && (
            <Button
              key="create"
              type="primary"
              icon={<PlusOutlined />}
              onClick={() => openForm()}
            >
              {t('common.create')}
            </Button>
          ),
        ]}
      />
      <AnnouncementForm
        open={formOpen}
        onOpenChange={setFormOpen}
        record={editing}
        onSuccess={reload}
      />
    </PageContainer>
  );
};

export default AnnouncementList;
