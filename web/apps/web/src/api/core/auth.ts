/**
 * 认证接口（Fortify + Sanctum，契约见 docs/backend-design.md §6.2、§7）。路径相对后台入口前缀（VITE_GLOB_API_URL）。
 */
import type { AppRequestConfig } from '#/api/request';

import { requestClient } from '#/api/request';

import { CSRF_COOKIE_URL } from '../client';

export namespace AuthApi {
  /** 登录参数：后端只接受这三个字段，多出任何字段都会返回 422 */
  export interface LoginParams {
    password: string;
    remember: boolean;
    username: string;
  }

  /** 登录结果：two_factor 为 true 时需要再提交两步验证 */
  export interface LoginResult {
    two_factor: boolean;
  }

  /** 两步验证：动态码与恢复码二选一 */
  export type TwoFactorChallengeParams =
    | { code: string }
    | { recovery_code: string };

  export interface ForgotPasswordParams {
    email: string;
  }

  export interface ResetPasswordParams {
    email: string;
    password: string;
    password_confirmation: string;
    token: string;
  }

  /** 找回密码、重置密码的结果 */
  export interface StatusResult {
    message: string;
  }
}

/** 已登录时请求只限未登录的接口返回 409，由调用方处理（直接进入系统），不提示 */
const guestOnlyConfig: AppRequestConfig = { silentError: [409] };

/**
 * 获取 CSRF Cookie（XSRF-TOKEN），登录前调用
 */
export async function getCsrfCookieApi() {
  return requestClient.get<null>(CSRF_COOKIE_URL);
}

/**
 * 登录
 */
export async function loginApi(params: AuthApi.LoginParams) {
  const { password, remember, username } = params;
  return requestClient.post<AuthApi.LoginResult>(
    '/login',
    { password, remember, username },
    guestOnlyConfig,
  );
}

/**
 * 两步验证（登录返回 two_factor: true 之后）
 */
export async function twoFactorChallengeApi(
  params: AuthApi.TwoFactorChallengeParams,
) {
  return requestClient.post<null>(
    '/two-factor-challenge',
    params,
    guestOnlyConfig,
  );
}

/**
 * 退出登录：会话可能已失效（401）或网络异常，结果都不提示，由调用方清理本地状态
 */
export async function logoutApi() {
  const config: AppRequestConfig = { silentError: true };
  return requestClient.post<null>('/logout', undefined, config);
}

/**
 * 发送重置密码邮件
 */
export async function forgotPasswordApi(params: AuthApi.ForgotPasswordParams) {
  return requestClient.post<AuthApi.StatusResult>('/forgot-password', params);
}

/**
 * 通过邮件中的链接重置密码
 */
export async function resetPasswordApi(params: AuthApi.ResetPasswordParams) {
  return requestClient.post<AuthApi.StatusResult>('/reset-password', params);
}

/**
 * 当前用户的有效权限码（目录、菜单、按钮的节点 name），登录时与启动时都重新拉取（§6.2）
 */
export async function getAccessCodesApi() {
  return requestClient.get<string[]>('/auth/codes');
}
