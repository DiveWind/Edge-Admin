import type zh from '../../zh-CN/pages/content';

const messages: Record<keyof typeof zh, string> = {
  'pages.content.articles.create': 'New Article',
  'pages.content.articles.edit': 'Edit Article',
  'pages.content.articles.title': 'Title',
  'pages.content.articles.category': 'Category',
  'pages.content.articles.summary': 'Summary',
  'pages.content.articles.cover': 'Cover',
  'pages.content.articles.content': 'Content',
  'pages.content.articles.author': 'Author',
  'pages.content.articles.department': 'Department',
  'pages.content.categories.create': 'New Category',
  'pages.content.categories.edit': 'Edit Category',
  'pages.content.categories.name': 'Category Name',
  'pages.content.categories.articlesCount': 'Articles',
  'pages.content.categories.deleteConfirm':
    'Delete this category? Categories that still have articles cannot be deleted.',
};

export default messages;
