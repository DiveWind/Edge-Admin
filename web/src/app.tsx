import { UserOutlined } from '@ant-design/icons';
import type { RequestConfig, RunTimeLayoutConfig } from '@umijs/max';
import { history, Link } from '@umijs/max';
import type React from 'react';
import {
  AvatarDropdown,
  ErrorBoundary,
  FeedbackBridge,
  Footer,
  LangDropdown,
  NoticeBell,
  OfflineBanner,
} from '@/components';
import Forbidden from '@/pages/exception/403';
import { getCurrentUser } from '@/services/auth';
import {
  clearToken,
  getToken,
  LOGIN_PATH,
  redirectToLogin,
} from '@/utils/auth';
import { toHttpError } from '@/utils/error';
import defaultSettings from '../config/defaultSettings';
import { errorConfig } from './requestErrorConfig';

export interface InitialState {
  currentUser?: API.CurrentUser;
  /** 重新拉取当前用户（登录后、修改资料后调用） */
  fetchUserInfo: () => Promise<API.CurrentUser | undefined>;
}

async function fetchUserInfo(): Promise<API.CurrentUser | undefined> {
  if (!getToken()) return undefined;
  try {
    const { data } = await getCurrentUser({ skipErrorHandler: true });
    return data;
  } catch (error) {
    if (toHttpError(error)?.status === 401) clearToken();
    return undefined;
  }
}

/**
 * 全局初始状态：当前用户（`GET /api/auth/me`）
 * @see https://umijs.org/docs/api/runtime-config#getinitialstate
 */
export async function getInitialState(): Promise<InitialState> {
  const state: InitialState = { fetchUserInfo };
  if (history.location.pathname === LOGIN_PATH) return state;

  const currentUser = await fetchUserInfo();
  if (!currentUser) redirectToLogin();
  return { ...state, currentUser };
}

/** @see https://procomponents.ant.design/components/layout */
export const layout: RunTimeLayoutConfig = ({ initialState }) => ({
  // 插件只消费 config 中 layout 的 title，logo / 布局模式等需在运行时传入
  ...defaultSettings,
  menuItemRender: (item, dom) =>
    item.path ? (
      <Link to={item.path} prefetch>
        {dom}
      </Link>
    ) : (
      dom
    ),
  actionsRender: () => [
    <NoticeBell key="notice" />,
    <LangDropdown key="lang" />,
  ],
  avatarProps: {
    src: initialState?.currentUser?.avatar || undefined,
    icon: <UserOutlined />,
    title: initialState?.currentUser?.name,
    size: 'small',
    render: (_, avatarChildren) => (
      <AvatarDropdown>{avatarChildren}</AvatarDropdown>
    ),
  },
  footerRender: () => <Footer />,
  onPageChange: () => {
    if (!initialState?.currentUser) redirectToLogin();
  },
  // 无权限访问路由时渲染（菜单已由 access 隐藏）
  unAccessible: <Forbidden />,
  ErrorBoundary,
});

/**
 * 请求配置：Bearer Token 注入 + Laravel 错误处理，见 requestErrorConfig.ts
 * @doc https://umijs.org/docs/max/request#配置
 */
export const request: RequestConfig = {
  timeout: 30_000,
  ...errorConfig,
};

export function rootContainer(container: React.ReactNode) {
  return (
    <>
      <OfflineBanner />
      <ErrorBoundary>{container}</ErrorBoundary>
    </>
  );
}

/** 位于 antd `<App>` 之内（umi 中 app.tsx 的 innerProvider 最先执行，处于最内层） */
export function innerProvider(container: React.ReactNode) {
  return (
    <>
      <FeedbackBridge />
      {container}
    </>
  );
}
