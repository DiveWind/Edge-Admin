import { MoonOutlined, SunOutlined } from '@ant-design/icons';
import { useAntdConfigSetter, useModel } from '@umijs/max';
import { Button, Tooltip } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';
import { storeTheme, themeAlgorithm } from '@/utils/theme';
import useHeaderActionStyles from './style';

/** 明 / 暗主题切换：同时更新全局 ConfigProvider 与布局 navTheme（initialState.theme），并持久化 */
export const ThemeSwitch: React.FC<{ className?: string }> = ({
  className,
}) => {
  const t = useT();
  const { styles, cx } = useHeaderActionStyles();
  const setAntdConfig = useAntdConfigSetter();
  const { initialState, setInitialState } = useModel('@@initialState');
  const dark = initialState?.theme === 'dark';
  const label = t(
    dark ? 'app.header.theme.toLight' : 'app.header.theme.toDark',
  );

  const toggle = () => {
    const next = dark ? 'light' : 'dark';
    storeTheme(next);
    setAntdConfig({ theme: { algorithm: themeAlgorithm(next) } });
    setInitialState((state) => (state ? { ...state, theme: next } : state));
  };

  return (
    <Tooltip title={label}>
      <Button
        type="text"
        className={cx(styles.action, className)}
        icon={dark ? <SunOutlined /> : <MoonOutlined />}
        aria-label={label}
        onClick={toggle}
      />
    </Tooltip>
  );
};
