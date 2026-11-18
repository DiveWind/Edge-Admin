import type { RouteRecordStringComponent } from '@vben/types';

import { requestClient } from '#/api/request';

/**
 * 当前用户可见的菜单树（Vben 路由结构，§6.2）；mixed 模式下由 router/access.ts 的 fetchMenuListAsync 调用，
 * 与静态路由按 name 合并。meta.title 是 i18n key，由前端翻译
 */
export async function getAllMenusApi() {
  return requestClient.get<RouteRecordStringComponent[]>('/menu/all');
}
