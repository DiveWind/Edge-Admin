import { Tag } from 'antd';
import type React from 'react';

/** HTTP 状态码：2xx/3xx 绿色，4xx 橙色，5xx 红色 */
const StatusCodeTag: React.FC<{ code: number }> = ({ code }) => {
  const color = code >= 500 ? 'error' : code >= 400 ? 'warning' : 'success';
  return <Tag color={color}>{code}</Tag>;
};

export default StatusCodeTag;
