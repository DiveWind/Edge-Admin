/** 文章（`/api/articles`），受数据权限约束 */
export interface Article {
  id: API.Id;
  category_id: API.Id;
  category: API.Brief | null;
  title: string;
  summary: string | null;
  cover: string | null;
  /** 列表接口可能不返回正文，编辑时以详情为准 */
  content?: string;
  status: API.PublishStatus;
  published_at: string | null;
  department_id: API.Id | null;
  department: API.Brief | null;
  author: API.Brief | null;
  created_by: API.Id;
  created_at: string;
  updated_at: string;
}

export interface ArticleFormValues {
  category_id: API.Id;
  title: string;
  summary?: string | null;
  cover?: string | null;
  content: string;
  status: API.PublishStatus;
}
