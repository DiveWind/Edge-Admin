import { useModel } from '@umijs/max';
import type { ThemeMode } from '@/utils/theme';

/** 当前明 / 暗主题（ThemeSwitch 切换后随之更新），用于 antd 体系外需要感知主题的地方（如图表） */
export function useThemeMode(): ThemeMode {
  const { initialState } = useModel('@@initialState');
  return initialState?.theme ?? 'light';
}
