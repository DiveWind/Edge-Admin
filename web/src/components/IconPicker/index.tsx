import { QuestionCircleOutlined } from '@ant-design/icons';
import { Button, Empty, Flex, Input, Popover, Tooltip } from 'antd';
import { createStyles } from 'antd-style';
import type React from 'react';
import { useMemo, useState } from 'react';
import { useT } from '@/utils/i18n';
import { MENU_ICON_NAMES, MENU_ICONS } from './icons';
import MenuIcon from './MenuIcon';

export { MENU_ICON_NAMES, MENU_ICONS } from './icons';
export { default as MenuIcon } from './MenuIcon';

const useStyles = createStyles(({ token, css }) => ({
  panel: css`
    width: 360px;
  `,
  grid: css`
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: ${token.marginXXS}px;
    max-height: 240px;
    overflow-y: auto;
  `,
  cell: css`
    font-size: ${token.fontSizeLG}px;
  `,
}));

export interface IconPickerProps {
  value?: string | null;
  onChange?: (value: string | null) => void;
  disabled?: boolean;
  id?: string;
}

/** 菜单图标选择器：Popover + 搜索 + 栅格，值为图标名（如 `SettingOutlined`），可放进 ProForm.Item */
const IconPicker: React.FC<IconPickerProps> = ({
  value,
  onChange,
  disabled,
  id,
}) => {
  const t = useT();
  const { styles } = useStyles();
  const [open, setOpen] = useState(false);
  const [keyword, setKeyword] = useState('');

  const names = useMemo(() => {
    const query = keyword.trim().toLowerCase();
    return query
      ? MENU_ICON_NAMES.filter((name) => name.toLowerCase().includes(query))
      : MENU_ICON_NAMES;
  }, [keyword]);

  const known = !value || Boolean(MENU_ICONS[value]);

  const select = (name: string) => {
    onChange?.(name);
    setOpen(false);
  };

  const content = (
    <Flex vertical gap="small" className={styles.panel}>
      <Input
        allowClear
        autoFocus
        value={keyword}
        placeholder={t('component.iconPicker.search')}
        onChange={(event) => setKeyword(event.target.value)}
      />
      {names.length ? (
        <div className={styles.grid}>
          {names.map((name) => (
            <Tooltip key={name} title={name} mouseEnterDelay={0.5}>
              <Button
                className={styles.cell}
                aria-label={name}
                type={name === value ? 'primary' : 'text'}
                icon={<MenuIcon name={name} />}
                onClick={() => select(name)}
              />
            </Tooltip>
          ))}
        </div>
      ) : (
        <Empty
          image={Empty.PRESENTED_IMAGE_SIMPLE}
          description={t('component.iconPicker.empty')}
        />
      )}
    </Flex>
  );

  return (
    <Popover
      trigger="click"
      placement="bottomLeft"
      open={open && !disabled}
      onOpenChange={setOpen}
      content={content}
      destroyOnHidden
    >
      <Input
        id={id}
        readOnly
        disabled={disabled}
        value={value ?? ''}
        placeholder={t('component.iconPicker.placeholder')}
        prefix={
          known ? (
            <MenuIcon name={value} />
          ) : (
            <Tooltip
              title={t('component.iconPicker.unknown', { name: value ?? '' })}
            >
              <QuestionCircleOutlined />
            </Tooltip>
          )
        }
        allowClear
        onChange={(event) => {
          // 只读输入框只会因清除按钮触发 onChange
          if (!event.target.value) onChange?.(null);
        }}
      />
    </Popover>
  );
};

export default IconPicker;
