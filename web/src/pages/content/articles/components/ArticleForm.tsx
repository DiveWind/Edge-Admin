import {
  DrawerForm,
  ProForm,
  ProFormRadio,
  ProFormSelect,
  ProFormText,
  ProFormTextArea,
} from '@ant-design/pro-components';
import { App, Form } from 'antd';
import type React from 'react';
import { ImageUpload } from '@/components';
import { usePublishStatusEnum } from '@/hooks/useValueEnums';
import { getArticleCategoryOptions, toSelectOptions } from '@/services/options';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import type { Article, ArticleFormValues } from '../data';
import { createArticle, getArticle, updateArticle } from '../service';

interface ArticleFormProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  record?: Article;
  onSuccess: () => void;
}

const toFormValues = (article: Article): ArticleFormValues => ({
  category_id: article.category_id,
  title: article.title,
  summary: article.summary,
  cover: article.cover,
  content: article.content ?? '',
  status: article.status,
});

const ArticleForm: React.FC<ArticleFormProps> = ({
  open,
  onOpenChange,
  record,
  onSuccess,
}) => {
  const t = useT();
  const { message } = App.useApp();
  const statusEnum = usePublishStatusEnum();
  const [form] = Form.useForm<ArticleFormValues>();

  const handleFinish = (values: ArticleFormValues) =>
    submitForm(form, async () => {
      if (record) {
        await updateArticle(record.id, values);
      } else {
        await createArticle(values);
      }
      message.success(t(record ? 'common.updated' : 'common.created'));
      onSuccess();
    });

  return (
    <DrawerForm<ArticleFormValues>
      title={t(
        record
          ? 'pages.content.articles.edit'
          : 'pages.content.articles.create',
      )}
      width={720}
      form={form}
      omitNil={false}
      open={open}
      onOpenChange={onOpenChange}
      drawerProps={{ destroyOnHidden: true }}
      initialValues={{ status: 'draft' }}
      request={
        record
          ? async () => toFormValues((await getArticle(record.id)).data)
          : undefined
      }
      onFinish={handleFinish}
    >
      <ProFormText
        name="title"
        label={t('pages.content.articles.title')}
        rules={[{ required: true }, { max: 200 }]}
      />
      <ProForm.Group>
        <ProFormSelect
          name="category_id"
          label={t('pages.content.articles.category')}
          width="md"
          request={() => getArticleCategoryOptions().then(toSelectOptions)}
          rules={[{ required: true }]}
        />
        <ProFormRadio.Group
          name="status"
          label={t('common.status')}
          valueEnum={statusEnum}
          rules={[{ required: true }]}
        />
      </ProForm.Group>
      <ProFormTextArea
        name="summary"
        label={t('pages.content.articles.summary')}
        fieldProps={{ rows: 2, maxLength: 500, showCount: true }}
      />
      <ProForm.Item name="cover" label={t('pages.content.articles.cover')}>
        <ImageUpload />
      </ProForm.Item>
      <ProFormTextArea
        name="content"
        label={t('pages.content.articles.content')}
        fieldProps={{ rows: 12 }}
        rules={[{ required: true }]}
      />
    </DrawerForm>
  );
};

export default ArticleForm;
