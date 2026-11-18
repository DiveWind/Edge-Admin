/**
 * 两步验证设置（Fortify，契约见 docs/backend-design.md §6.2、§7.2）。
 *
 * 这些接口都需要先确认密码：未确认时返回 423，请求层会弹出确认密码框，确认后自动重试。
 * 开启后需要用动态码确认才生效（confirm）。
 */
import { requestClient } from '#/api/request';

export namespace TwoFactorApi {
  /** 二维码：未开启时为空数组 */
  export interface QrCode {
    svg: string;
    url: string;
  }

  export interface SecretKey {
    secretKey: string;
  }
}

/**
 * 开启（生成密钥与恢复码，待确认）
 */
export async function enableTwoFactorApi() {
  return requestClient.post<null>('/user/two-factor-authentication');
}

/**
 * 用动态码确认开启
 */
export async function confirmTwoFactorApi(code: string) {
  return requestClient.post<null>('/user/confirmed-two-factor-authentication', {
    code,
  });
}

/**
 * 关闭
 */
export async function disableTwoFactorApi() {
  return requestClient.delete<null>('/user/two-factor-authentication');
}

/**
 * 二维码（未开启时返回空数组）
 */
export async function getTwoFactorQrCodeApi() {
  return requestClient.get<[] | TwoFactorApi.QrCode>(
    '/user/two-factor-qr-code',
  );
}

/**
 * 密钥（用于手动输入到身份验证器）
 */
export async function getTwoFactorSecretKeyApi() {
  return requestClient.get<TwoFactorApi.SecretKey>(
    '/user/two-factor-secret-key',
  );
}

/**
 * 恢复码列表
 */
export async function getRecoveryCodesApi() {
  return requestClient.get<string[]>('/user/two-factor-recovery-codes');
}

/**
 * 重新生成恢复码（旧的恢复码全部失效）
 */
export async function regenerateRecoveryCodesApi() {
  return requestClient.post<null>('/user/two-factor-recovery-codes');
}
