import { Navigate, useAccess, useModel } from '@umijs/max';
import type React from 'react';
import Exception403 from '@/pages/exception/403';
import { findFirstMenuPath } from '@/utils/menu';
import { canAccessPath } from '@/utils/route';
import routes from '../../../config/routes';

/**
 * `/` 落地页：按服务端菜单顺序跳到当前用户第一个可访问的站内页面（有静态路由且通过路由级权限），
 * 一个都没有时显示 403。
 */
const Home: React.FC = () => {
  const access = useAccess();
  const { initialState } = useModel('@@initialState');
  const path = findFirstMenuPath(initialState?.menus ?? [], (menuPath) =>
    canAccessPath(routes, menuPath, (route) =>
      route.access === 'routeFilter'
        ? access.routeFilter(route)
        : access.isLoggedIn,
    ),
  );
  return path ? <Navigate to={path} replace /> : <Exception403 />;
};

export default Home;
