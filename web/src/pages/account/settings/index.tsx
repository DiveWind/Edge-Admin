import { PageContainer, ProCard } from '@ant-design/pro-components';
import { useModel } from '@umijs/max';
import type React from 'react';
import { useState } from 'react';
import { useT } from '@/utils/i18n';
import BasicSettings from './components/BasicSettings';
import PasswordSettings from './components/PasswordSettings';

type TabKey = 'basic' | 'password';

/** 个人设置：只要求登录，不在菜单中显示（入口在头像下拉） */
const AccountSettings: React.FC = () => {
  const t = useT();
  const { initialState } = useModel('@@initialState');
  const [activeKey, setActiveKey] = useState<TabKey>('basic');
  const currentUser = initialState?.currentUser;

  if (!currentUser) return null;

  return (
    <PageContainer
      tabList={[
        { key: 'basic', tab: t('pages.account.tab.basic') },
        { key: 'password', tab: t('pages.account.tab.password') },
      ]}
      tabActiveKey={activeKey}
      onTabChange={(key) => setActiveKey(key as TabKey)}
    >
      <ProCard>
        {activeKey === 'basic' ? (
          <BasicSettings currentUser={currentUser} />
        ) : (
          <PasswordSettings />
        )}
      </ProCard>
    </PageContainer>
  );
};

export default AccountSettings;
