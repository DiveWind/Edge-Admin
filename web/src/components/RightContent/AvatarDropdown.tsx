import { LogoutOutlined, UserOutlined } from '@ant-design/icons';
import { history, useModel } from '@umijs/max';
import type { MenuProps } from 'antd';
import { Spin } from 'antd';
import type React from 'react';
import { logout } from '@/services/auth';
import { clearToken, LOGIN_PATH } from '@/utils/auth';
import { useT } from '@/utils/i18n';
import HeaderDropdown from '../HeaderDropdown';

export const AvatarDropdown: React.FC<{ children?: React.ReactNode }> = ({
  children,
}) => {
  const t = useT();
  const { initialState, setInitialState } = useModel('@@initialState');

  if (!initialState?.currentUser) {
    return <Spin size="small" />;
  }

  const handleLogout = async () => {
    try {
      // 吊销服务端 token；失败（如 token 已失效）不影响本地退出
      await logout({ skipErrorHandler: true });
    } finally {
      clearToken();
      setInitialState((state) =>
        state ? { ...state, currentUser: undefined, menus: [] } : state,
      );
      history.replace(LOGIN_PATH);
    }
  };

  const items: MenuProps['items'] = [
    {
      key: 'settings',
      icon: <UserOutlined />,
      label: t('app.header.accountSettings'),
    },
    { type: 'divider' },
    { key: 'logout', icon: <LogoutOutlined />, label: t('app.header.logout') },
  ];

  const onClick: MenuProps['onClick'] = ({ key }) => {
    if (key === 'logout') {
      handleLogout();
      return;
    }
    history.push('/account/settings');
  };

  return (
    <HeaderDropdown menu={{ selectedKeys: [], onClick, items }}>
      {children}
    </HeaderDropdown>
  );
};
