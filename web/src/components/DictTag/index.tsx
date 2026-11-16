import { Tag } from 'antd';
import type React from 'react';
import { useDict } from '@/hooks/useDict';

export interface DictTagProps {
  /** 字典编码，如 `article_source` */
  code: string;
  value?: string | null;
}

/**
 * 按字典 value 渲染带颜色的标签（颜色取字典项的 color）。
 * 值为空显示 `-`；值不在字典中（字典项被停用 / 删除）时原样显示 value。
 */
const DictTag: React.FC<DictTagProps> = ({ code, value }) => {
  const dict = useDict(code);
  if (value === null || value === undefined || value === '') return <>-</>;
  const item = dict.find(value);
  return <Tag color={item?.color || undefined}>{item?.label ?? value}</Tag>;
};

export default DictTag;
