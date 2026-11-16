import { GlobalOutlined } from '@ant-design/icons';
import { getLocale, setLocale } from '@umijs/max';
import type { MenuProps } from 'antd';
import { Button } from 'antd';
import type React from 'react';
import { useT } from '@/utils/i18n';
import HeaderDropdown from '../HeaderDropdown';
import useHeaderActionStyles from './style';

/** 支持的语言，与 src/locales 下的文件一一对应 */
const LOCALES = [
  { key: 'zh-CN', label: '简体中文' },
  { key: 'en-US', label: 'English' },
];

export const LangDropdown: React.FC<{ className?: string }> = ({
  className,
}) => {
  const t = useT();
  const { styles, cx } = useHeaderActionStyles();
  const current = getLocale();

  const items: MenuProps['items'] = LOCALES.map(({ key, label }) => ({
    key,
    label,
  }));

  return (
    <HeaderDropdown
      menu={{
        selectable: true,
        selectedKeys: [current],
        items,
        onClick: ({ key }) => setLocale(key, false),
      }}
    >
      <Button
        type="text"
        className={cx(styles.action, className)}
        icon={<GlobalOutlined />}
        aria-label={t('app.header.language')}
      />
    </HeaderDropdown>
  );
};
