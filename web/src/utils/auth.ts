import { history } from '@umijs/max';

/**
 * 登录态相关的小工具：Sanctum API Token 的本地存储 + 跳转登录页。
 * 只在这里读写 localStorage，其他地方一律调用本模块。
 */
const TOKEN_KEY = 'edge_admin_token';

export const LOGIN_PATH = '/user/login';

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}

/** 跳转登录页，并带上当前地址作为 redirect（已在登录页时不处理） */
export function redirectToLogin(): void {
  const { pathname, search, hash } = history.location;
  if (pathname === LOGIN_PATH) return;
  const redirect = encodeURIComponent(pathname + search + hash);
  history.replace(`${LOGIN_PATH}?redirect=${redirect}`);
}

/** 校验登录后的回跳地址，只允许站内相对路径，防止开放重定向 */
export function getSafeRedirect(redirect: string | null): string {
  if (!redirect?.startsWith('/') || redirect.startsWith('//')) return '/';
  try {
    const url = new URL(redirect, window.location.origin);
    if (url.origin !== window.location.origin) return '/';
    return `${url.pathname}${url.search}${url.hash}`;
  } catch {
    return '/';
  }
}
