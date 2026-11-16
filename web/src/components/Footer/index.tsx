import { DefaultFooter } from '@ant-design/pro-components';
import { useModel } from '@umijs/max';
import type React from 'react';
import { DEFAULT_SITE_SETTINGS } from '@/utils/settings';

/** 页脚：版权（站点名称）+ 备案号（系统参数 `site.icp`，为空不展示） */
const Footer: React.FC = () => {
  const { initialState } = useModel('@@initialState');
  const site = initialState?.site ?? DEFAULT_SITE_SETTINGS;
  return (
    <DefaultFooter
      style={{ background: 'none' }}
      copyright={`${new Date().getFullYear()} ${site.name}`}
      links={
        site.icp
          ? [
              {
                key: 'icp',
                title: site.icp,
                href: 'https://beian.miit.gov.cn/',
                blankTarget: true,
              },
            ]
          : false
      }
    />
  );
};

export default Footer;
