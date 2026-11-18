import { defineOverridesPreferences } from '@vben/preferences';

/** public/ 下的静态文件地址（BASE_URL 即 VITE_BASE，以 / 结尾） */
const asset = (file: string) => `${import.meta.env.BASE_URL}${file}`;

/**
 * @description 项目配置文件
 * 只需要覆盖项目中的一部分配置，不需要的配置不用覆盖，会自动使用默认配置
 * !!! 更改配置后请清空缓存，否则可能不生效
 */
export const overridesPreferences = defineOverridesPreferences({
  // overrides
  app: {
    // 未设置头像时使用的本地默认头像
    defaultAvatar: asset('avatar.svg'),
    name: import.meta.env.VITE_APP_TITLE,
  },
  // 不带品牌：版权方为应用标题，无外部链接
  copyright: {
    companyName: import.meta.env.VITE_APP_TITLE,
    companySiteLink: '',
    date: String(new Date().getFullYear()),
  },
  logo: {
    source: asset('logo.svg'),
  },
  widget: {
    // 通知中心尚未接入后端
    notification: false,
  },
});
