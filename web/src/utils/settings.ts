import defaultSettings from '../../config/defaultSettings';

/**
 * 站点级公开参数（`GET /api/options/settings`，无需登录），在 getInitialState 中加载。
 * 接口失败或参数缺失时回退到默认值，页面不会因此不可用。
 */
export interface SiteSettings {
  /** `site.name`：布局标题、登录页标题、document.title */
  name: string;
  /** `site.login_notice`：登录页公告，为空不展示 */
  loginNotice: string;
  /** `site.icp`：页脚备案号，为空不展示 */
  icp: string;
}

export const DEFAULT_SITE_SETTINGS: SiteSettings = {
  name: defaultSettings.title,
  loginNotice: '',
  icp: '',
};

const text = (value: unknown): string =>
  typeof value === 'string' ? value.trim() : '';

export function toSiteSettings(
  settings: API.PublicSettings | null | undefined,
): SiteSettings {
  return {
    name: text(settings?.['site.name']) || DEFAULT_SITE_SETTINGS.name,
    loginNotice: text(settings?.['site.login_notice']),
    icp: text(settings?.['site.icp']),
  };
}
