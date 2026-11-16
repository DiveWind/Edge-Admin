import { theme } from 'antd';

/**
 * 明 / 暗主题。偏好存 localStorage；生效方式：
 * - 全局（含登录页等无布局页面）：Umi antd 插件的 ConfigProvider `theme.algorithm`
 *   （初始值见 app.tsx 的 `antd` 运行时配置，切换用 `useAntdConfigSetter`）；
 * - 布局：ProLayout `navTheme`（`realDark` 时 ProLayout 内部同样使用暗色算法）。
 */
export type ThemeMode = 'light' | 'dark';

const THEME_KEY = 'edge_admin_theme';

export function getStoredTheme(): ThemeMode {
  try {
    return localStorage.getItem(THEME_KEY) === 'dark' ? 'dark' : 'light';
  } catch {
    return 'light';
  }
}

export function storeTheme(mode: ThemeMode): void {
  try {
    localStorage.setItem(THEME_KEY, mode);
  } catch {
    // 隐私模式等无法写入时只在本次会话生效
  }
}

export function themeAlgorithm(mode: ThemeMode) {
  return mode === 'dark' ? theme.darkAlgorithm : theme.defaultAlgorithm;
}

/** ProLayout 的 navTheme */
export function navTheme(mode: ThemeMode): 'light' | 'realDark' {
  return mode === 'dark' ? 'realDark' : 'light';
}
