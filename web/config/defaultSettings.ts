import type { ProLayoutProps } from '@ant-design/pro-components';

/**
 * ProLayout 默认设置
 * @doc https://procomponents.ant.design/components/layout
 */
const Settings: ProLayoutProps & {
  title: string;
  logo: string;
} = {
  navTheme: 'light',
  colorPrimary: '#1677ff',
  layout: 'mix',
  contentWidth: 'Fluid',
  fixedHeader: false,
  fixSiderbar: true,
  colorWeak: false,
  title: 'Edge Admin',
  logo: '/logo.svg',
  iconfontUrl: '',
  token: {},
};

export default Settings;
