/**
 * Sanctum API Token 的本地存储。只在这里读写 localStorage，其他地方一律调用本模块。
 */
const TOKEN_KEY = 'edge_admin_token';

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}
