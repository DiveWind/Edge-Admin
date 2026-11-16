import { PlusOutlined } from '@ant-design/icons';
import type { ActionType, ProColumns } from '@ant-design/pro-components';
import { PageContainer, ProTable } from '@ant-design/pro-components';
import { useAccess } from '@umijs/max';
import { Button, Typography } from 'antd';
import type React from 'react';
import { useRef, useState } from 'react';
import { DeleteAction, DictTag } from '@/components';
import { useDict } from '@/hooks/useDict';
import { usePublishStatusEnum } from '@/hooks/useValueEnums';
import { getArticleCategoryOptions, toSelectOptions } from '@/services/options';
import { useT } from '@/utils/i18n';
import { tableRequest } from '@/utils/table';
import ArticleForm from './components/ArticleForm';
import type { Article } from './data';
import { deleteArticle, queryArticles } from './service';

/**
 * 业务对接示例：标准 CRUD + 数据权限（列表与单条均由后端按 data_scope 过滤，越权返回 403）
 */
const ArticleList: React.FC = () => {
  const t = useT();
  const access = useAccess();
  const statusEnum = usePublishStatusEnum();
  // 字典驱动的列：valueEnum 用于搜索下拉，DictTag 按字典颜色渲染
  const sourceDict = useDict('article_source');
  const actionRef = useRef<ActionType>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Article>();

  const reload = () => actionRef.current?.reload();

  const openForm = (record?: Article) => {
    setEditing(record);
    setFormOpen(true);
  };

  const columns: ProColumns<Article>[] = [
    {
      title: t('pages.content.articles.cover'),
      dataIndex: 'cover',
      valueType: 'image',
      search: false,
      width: 88,
    },
    {
      title: t('pages.content.articles.title'),
      dataIndex: 'title',
      ellipsis: true,
      width: 240,
    },
    {
      title: t('pages.content.articles.category'),
      dataIndex: 'category_id',
      valueType: 'select',
      request: () => getArticleCategoryOptions().then(toSelectOptions),
      render: (_, record) => record.category?.name ?? '-',
    },
    {
      title: t('common.status'),
      dataIndex: 'status',
      valueType: 'select',
      valueEnum: statusEnum,
    },
    {
      title: t('pages.content.articles.source'),
      dataIndex: 'source',
      valueType: 'select',
      valueEnum: sourceDict.valueEnum,
      width: 100,
      render: (_, record) => (
        <DictTag code="article_source" value={record.source} />
      ),
    },
    {
      title: t('pages.content.articles.author'),
      dataIndex: ['author', 'name'],
      search: false,
      width: 120,
    },
    {
      title: t('pages.content.articles.department'),
      dataIndex: ['department', 'name'],
      search: false,
    },
    {
      title: t('common.publishedAt'),
      dataIndex: 'published_at',
      valueType: 'dateTime',
      width: 170,
      search: false,
      sorter: true,
    },
    {
      title: t('common.createdAt'),
      dataIndex: 'created_at',
      key: 'created_at',
      valueType: 'dateTime',
      width: 170,
      search: false,
      sorter: true,
    },
    {
      title: t('common.actions'),
      valueType: 'option',
      width: 120,
      render: (_, record) => [
        access.can('content.article.update') && (
          <Typography.Link key="edit" onClick={() => openForm(record)}>
            {t('common.edit')}
          </Typography.Link>
        ),
        access.can('content.article.delete') && (
          <DeleteAction
            key="delete"
            onConfirm={() => deleteArticle(record.id)}
            onSuccess={reload}
          />
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<Article>
        rowKey="id"
        actionRef={actionRef}
        columns={columns}
        request={tableRequest(queryArticles)}
        // 创建时间默认隐藏（列设置中可打开），保证常用列在常见宽度下完整显示
        columnsState={{ defaultValue: { created_at: { show: false } } }}
        toolBarRender={() => [
          access.can('content.article.create') && (
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
      <ArticleForm
        open={formOpen}
        onOpenChange={setFormOpen}
        record={editing}
        onSuccess={reload}
      />
    </PageContainer>
  );
};

export default ArticleList;
