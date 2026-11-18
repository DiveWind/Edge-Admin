/**
 * 当前用户：用户信息、个人资料、密码、确认密码（契约见 docs/backend-design.md §6.2、§7.2）。
 */
import type { UserInfo } from '@vben/types';

import type { AppRequestConfig } from '#/api/request';

import { requestClient } from '#/api/request';

export namespace UserApi {
  /** 修改个人资料：未提交的 email、avatar 保持不变，提交 null 表示清空 */
  export interface UpdateProfileParams {
    avatar?: null | string;
    email?: null | string;
    name: string;
  }

  export interface UpdatePasswordParams {
    current_password: string;
    password: string;
    password_confirmation: string;
  }

  export interface ConfirmedPasswordStatus {
    confirmed: boolean;
  }
}

/**
 * 获取当前用户信息；启动时用它判断会话是否有效（401 即未登录）
 */
export async function getUserInfoApi() {
  return requestClient.get<UserInfo>('/user/info');
}

/**
 * 修改个人资料（姓名、邮箱、头像地址）
 */
export async function updateProfileApi(params: UserApi.UpdateProfileParams) {
  return requestClient.put<null>('/user/profile-information', params);
}

/**
 * 修改密码（其他设备上的会话随之失效）
 */
export async function updatePasswordApi(params: UserApi.UpdatePasswordParams) {
  return requestClient.put<null>('/user/password', params);
}

/**
 * 最近是否确认过密码（敏感操作前需要确认，否则返回 423）
 */
export async function getConfirmedPasswordStatusApi() {
  return requestClient.get<UserApi.ConfirmedPasswordStatus>(
    '/user/confirmed-password-status',
  );
}

/**
 * 确认密码。密码错误（422）不自动提示，由确认框显示具体原因
 */
export async function confirmPasswordApi(password: string) {
  const config: AppRequestConfig = { silentError: [422] };
  return requestClient.post<null>(
    '/user/confirm-password',
    { password },
    config,
  );
}
