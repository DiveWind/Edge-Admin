/** 文章分类（`/api/article-categories`） */
export interface Category {
  id: API.Id;
  name: string;
  sort: number;
  status: API.Status;
  /** 分类下文章数（> 0 时不可删除） */
  articles_count: number;
  created_at: string;
}

export interface CategoryFormValues {
  name: string;
  sort: number;
  status: API.Status;
}
