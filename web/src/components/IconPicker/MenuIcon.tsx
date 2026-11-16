import type React from 'react';
import { createElement } from 'react';
import { MENU_ICONS } from './icons';

/** 按图标名渲染菜单图标；未收录或为空时渲染 fallback（默认不渲染） */
const MenuIcon: React.FC<{
  name?: string | null;
  fallback?: React.ReactNode;
}> = ({ name, fallback = null }) => {
  const icon = name ? MENU_ICONS[name] : undefined;
  return icon ? createElement(icon) : fallback;
};

export default MenuIcon;
