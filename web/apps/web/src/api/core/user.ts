/**
 * 当前用户：用户信息、个人资料、密码、确认密码（契约见 docs/backend-design.md §6.2、§7.2）。
 */
import type { UserInfo as VbenUserInfo } from '@vben/types';

import type { AppRequestConfig } from '#/api/request';

import { requestClient } from '#/api/request';

export namespace UserApi {
  /** GET /user/info：Vben 的用户信息，加上邮箱与两步验证状态（§6.2） */
  export interface UserInfo extends VbenUserInfo {
    /** 邮箱，未设置时为 null */
    email: null | string;
    /** 两步验证已确认开启（开启后未用动态码确认时为 false） */
    twoFactorEnabled: boolean;
  }

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
}

/**
 * 获取当前用户信息；启动时用它判断会话是否有效（401 即未登录）
 */
export async function getUserInfoApi() {
  return requestClient.get<UserApi.UserInfo>('/user/info');
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
