import type { RouteRecordRaw } from 'vue-router';

import { $t } from '#/locales';

/**
 * 工作台：路径与后端 GET /user/info 的 homePath 默认值（/analytics）、preferences.app.defaultHomePath 一致
 */
const routes: RouteRecordRaw[] = [
  {
    name: 'Dashboard',
    path: '/analytics',
    component: () => import('#/views/dashboard/index.vue'),
    meta: {
      affixTab: true,
      icon: 'lucide:layout-dashboard',
      order: -1,
      title: $t('page.dashboard.title'),
    },
  },
];

export default routes;
