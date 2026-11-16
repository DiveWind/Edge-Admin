import { UserOutlined } from '@ant-design/icons';
import type {
  RequestConfig,
  RunTimeLayoutConfig,
  RuntimeAntdConfig,
} from '@umijs/max';
import { history, Link } from '@umijs/max';
import type React from 'react';
import {
  AvatarDropdown,
  ErrorBoundary,
  FeedbackBridge,
  Footer,
  GlobalStyle,
  LangDropdown,
  NoticeBell,
  OfflineBanner,
  ThemeSwitch,
} from '@/components';
import Forbidden from '@/pages/exception/403';
import { getCurrentUser } from '@/services/auth';
import { getPublicSettings } from '@/services/options';
import {
  clearToken,
  getToken,
  LOGIN_PATH,
  redirectToLogin,
} from '@/utils/auth';
import { toHttpError } from '@/utils/error';
import type { SiteSettings } from '@/utils/settings';
import { DEFAULT_SITE_SETTINGS, toSiteSettings } from '@/utils/settings';
import type { ThemeMode } from '@/utils/theme';
import { getStoredTheme, navTheme, themeAlgorithm } from '@/utils/theme';
import defaultSettings from '../config/defaultSettings';
import { errorConfig } from './requestErrorConfig';

export interface InitialState {
  currentUser?: API.CurrentUser;
  /** 重新拉取当前用户（登录后、修改资料后调用） */
  fetchUserInfo: () => Promise<API.CurrentUser | undefined>;
  /** 公开系统参数（站点名称、登录页公告、备案号） */
  site: SiteSettings;
  /** 重新拉取公开系统参数（修改系统参数后调用） */
  fetchSiteSettings: () => Promise<SiteSettings>;
  /** 明 / 暗主题（ThemeSwitch 切换） */
  theme: ThemeMode;
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

/** 公开系统参数，失败时回退默认配置（不阻塞页面） */
async function fetchSiteSettings(): Promise<SiteSettings> {
  try {
    const { data } = await getPublicSettings({ skipErrorHandler: true });
    return toSiteSettings(data);
  } catch {
    return DEFAULT_SITE_SETTINGS;
  }
}

/**
 * 全局初始状态：当前用户（`GET /api/auth/me`）与公开系统参数（`GET /api/options/settings`）并行加载
 * @see https://umijs.org/docs/api/runtime-config#getinitialstate
 */
export async function getInitialState(): Promise<InitialState> {
  const isLoginPage = history.location.pathname === LOGIN_PATH;
  const [site, currentUser] = await Promise.all([
    fetchSiteSettings(),
    isLoginPage ? Promise.resolve(undefined) : fetchUserInfo(),
  ]);
  if (!isLoginPage && !currentUser) redirectToLogin();
  return {
    currentUser,
    fetchUserInfo,
    site,
    fetchSiteSettings,
    theme: getStoredTheme(),
  };
}

/**
 * antd 全局配置（Umi antd 插件）：按本地偏好设置初始明 / 暗算法，切换见 ThemeSwitch
 * @doc https://umijs.org/docs/max/antd#运行时配置
 */
export const antd: RuntimeAntdConfig = (memo) => {
  memo.theme ??= {};
  memo.theme.algorithm = themeAlgorithm(getStoredTheme());
  return memo;
};

/** @see https://procomponents.ant.design/components/layout */
export const layout: RunTimeLayoutConfig = ({ initialState }) => ({
  // 插件只消费 config 中 layout 的 title，logo / 布局模式等需在运行时传入
  ...defaultSettings,
  title: initialState?.site.name ?? defaultSettings.title,
  navTheme: navTheme(initialState?.theme ?? 'light'),
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
    <ThemeSwitch key="theme" />,
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
      <GlobalStyle />
      <FeedbackBridge />
      {container}
    </>
  );
}
