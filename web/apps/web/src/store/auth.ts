import type { Recordable, UserInfo } from '@vben/types';

import type { AuthApi } from '#/api';

import { ref } from 'vue';
import { useRouter } from 'vue-router';

import { prompt, VbenInputPassword } from '@vben/common-ui';
import { LOGIN_PATH } from '@vben/constants';
import { preferences } from '@vben/preferences';
import { resetAllStores, useAccessStore, useUserStore } from '@vben/stores';

import { message, notification } from 'antdv-next';
import { defineStore } from 'pinia';

import {
  confirmPasswordApi,
  getCsrfCookieApi,
  getUserInfoApi,
  loginApi,
  logoutApi,
  twoFactorChallengeApi,
} from '#/api';
import { getErrorMessage, isErrorStatus } from '#/api/request';
import { $t } from '#/locales';

/**
 * 已登录标记：会话保存在 HttpOnly Cookie 中，前端读不到，因此沿用 Vben access store 的 accessToken 字段
 * （已持久化，resetAllStores() 时清空）存放一个固定值，表示“上次已登录”。它不是令牌，不会发给后端。
 *
 * 启动（刷新）后路由守卫看到标记就请求 GET /user/info 确认会话仍然有效：成功则进入系统，失败（401 等）清除标记回登录页。
 * 没有标记时直接进入登录页；若会话其实仍有效，登录返回 409，同样直接进入系统。
 */
export const SESSION_MARKER = 'session';

/** 两步验证页 */
export const TWO_FACTOR_PATH = '/auth/two-factor';

export const useAuthStore = defineStore('auth', () => {
  const accessStore = useAccessStore();
  const userStore = useUserStore();
  const router = useRouter();

  const loginLoading = ref(false);

  /**
   * 登录：获取 CSRF Cookie → POST /login。
   * - two_factor 为 false：进入系统（completeLogin）；
   * - two_factor 为 true：跳转两步验证页，由 authTwoFactor 完成登录；
   * - 409（会话仍有效）：直接进入系统。
   * 其他错误抛给调用方（422 的字段错误由登录页回填到表单）。
   */
  async function authLogin(
    params: Recordable<any>,
    onSuccess?: () => Promise<void> | void,
  ) {
    let userInfo: null | UserInfo = null;
    let twoFactor = false;
    try {
      loginLoading.value = true;
      await getCsrfCookieApi();

      const credentials: AuthApi.LoginParams = {
        password: String(params.password ?? ''),
        remember: Boolean(params.remember),
        username: String(params.username ?? ''),
      };

      let result: AuthApi.LoginResult | null = null;
      try {
        result = await loginApi(credentials);
      } catch (error) {
        if (!isErrorStatus(error, 409)) {
          throw error;
        }
      }

      if (result?.two_factor) {
        twoFactor = true;
        // 登录页的 redirect 参数带到两步验证页；在登录过期弹窗中登录时同样跳转（弹窗随之关闭），完成后回到当前页面
        const current = router.currentRoute.value;
        accessStore.setLoginExpired(false);
        await router.push({
          path: TWO_FACTOR_PATH,
          query: current.path.startsWith('/auth')
            ? current.query
            : { redirect: encodeURIComponent(current.fullPath) },
        });
      } else {
        userInfo = await completeLogin(onSuccess);
      }
    } finally {
      loginLoading.value = false;
    }

    return { twoFactor, userInfo };
  }

  /**
   * 两步验证：提交动态码或恢复码完成登录；409（已登录）同样直接进入系统。
   */
  async function authTwoFactor(
    params: AuthApi.TwoFactorChallengeParams,
    onSuccess?: () => Promise<void> | void,
  ) {
    try {
      loginLoading.value = true;
      try {
        await twoFactorChallengeApi(params);
      } catch (error) {
        if (!isErrorStatus(error, 409)) {
          throw error;
        }
      }
      return { userInfo: await completeLogin(onSuccess) };
    } finally {
      loginLoading.value = false;
    }
  }

  /**
   * 登录成功：设置已登录标记，获取用户信息，跳转到 redirect 参数或 homePath。
   * 菜单与按钮权限暂用前端静态路由模式（GET /auth/codes 第 5 步才有）。
   */
  async function completeLogin(onSuccess?: () => Promise<void> | void) {
    accessStore.setAccessToken(SESSION_MARKER);
    const userInfo = await fetchUserInfo();
    accessStore.setAccessCodes([]);

    if (accessStore.loginExpired) {
      accessStore.setLoginExpired(false);
    } else if (onSuccess) {
      await onSuccess();
    } else {
      const redirect = router.currentRoute.value.query?.redirect;
      await router.push(
        typeof redirect === 'string' && redirect !== ''
          ? decodeURIComponent(redirect)
          : userInfo.homePath || preferences.app.defaultHomePath,
      );
    }

    if (userInfo.realName) {
      notification.success({
        description: `${$t('authentication.loginSuccessDesc')}:${userInfo.realName}`,
        duration: 3,
        title: $t('authentication.loginSuccess'),
      });
    }

    return userInfo;
  }

  /**
   * 退出登录：先清除已登录标记（登出请求失败时不再触发 401 处理），再请求 POST /logout，最后回到登录页。
   */
  async function logout(redirect: boolean = true) {
    accessStore.setAccessToken(null);
    try {
      await logoutApi();
    } catch {
      // 会话已失效或网络异常：本地状态照常清理
    }
    await toLoginPage(redirect);
  }

  /**
   * 401（会话失效、账号已停用）：由请求层调用。
   * - 没有已登录标记：登录页、登出过程中的 401，不处理；
   * - 启动阶段（权限尚未生成）：只清除标记，由路由守卫跳转登录页；
   * - 已进入系统：按偏好设置弹出重新登录框，或回到登录页（会话已不存在，不再请求登出接口）。
   */
  async function handleUnauthorized() {
    if (!accessStore.accessToken) {
      return;
    }
    accessStore.setAccessToken(null);

    if (!accessStore.isAccessChecked) {
      resetAllStores();
      return;
    }
    if (preferences.app.loginExpiredMode === 'modal') {
      accessStore.setLoginExpired(true);
      return;
    }
    await toLoginPage(true);
  }

  /**
   * 423：弹出确认密码框，确认成功返回 true，取消返回 false。密码错误时提示原因，弹窗保持打开。
   */
  async function confirmPassword(): Promise<boolean> {
    try {
      await prompt<string>({
        async beforeClose({ isConfirm, value }) {
          if (!isConfirm) {
            return true;
          }
          if (!value) {
            message.warning($t('authentication.passwordTip'));
            return false;
          }
          try {
            await confirmPasswordApi(value);
            return true;
          } catch (error) {
            message.error(
              getErrorMessage(error) ?? $t('auth.confirmPassword.failed'),
            );
            return false;
          }
        },
        component: VbenInputPassword,
        componentProps: {
          autocomplete: 'current-password',
          placeholder: $t('authentication.passwordTip'),
        },
        content: $t('auth.confirmPassword.content'),
        icon: 'warning',
        title: $t('auth.confirmPassword.title'),
      });
      return true;
    } catch {
      return false;
    }
  }

  async function toLoginPage(redirect: boolean) {
    resetAllStores();
    accessStore.setLoginExpired(false);

    // 回登录页带上当前路由地址
    const current = router.currentRoute.value.fullPath;
    await router.replace({
      path: LOGIN_PATH,
      query:
        redirect && !current.startsWith('/auth')
          ? { redirect: encodeURIComponent(current) }
          : {},
    });
  }

  async function fetchUserInfo() {
    const userInfo = await getUserInfoApi();
    userStore.setUserInfo(userInfo);
    return userInfo;
  }

  function $reset() {
    loginLoading.value = false;
  }

  return {
    $reset,
    authLogin,
    authTwoFactor,
    confirmPassword,
    fetchUserInfo,
    handleUnauthorized,
    loginLoading,
    logout,
  };
});
