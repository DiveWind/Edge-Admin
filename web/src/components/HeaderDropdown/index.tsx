import type { DropdownProps } from 'antd';
import { Dropdown } from 'antd';
import { createStyles } from 'antd-style';
import type React from 'react';

const useStyles = createStyles(({ token }) => ({
  dropdown: {
    [`@media screen and (max-width: ${token.screenXS}px)`]: {
      width: '100%',
    },
  },
}));

/** 头部右侧下拉菜单（语言、头像）的统一外观 */
const HeaderDropdown: React.FC<DropdownProps> = (props) => {
  const { styles } = useStyles();
  return (
    <Dropdown
      placement="bottomRight"
      arrow
      classNames={{ root: styles.dropdown }}
      {...props}
    />
  );
};

export default HeaderDropdown;
