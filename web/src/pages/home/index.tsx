import { Navigate, useAccess } from '@umijs/max';
import type React from 'react';
import Exception403 from '@/pages/exception/403';
import { findFirstMenuPath } from '@/utils/route';
import routes from '../../../config/routes';

/** `/` 落地页：跳到当前用户第一个有权限的菜单；一个都没有时显示 403 */
const Home: React.FC = () => {
  const access = useAccess();
  const path = findFirstMenuPath(routes, (route) =>
    route.access === 'routeFilter'
      ? access.routeFilter(route)
      : access.isLoggedIn,
  );
  return path ? <Navigate to={path} replace /> : <Exception403 />;
};

export default Home;
