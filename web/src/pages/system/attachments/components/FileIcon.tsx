import {
  FileExcelOutlined,
  FileOutlined,
  FilePdfOutlined,
  FilePptOutlined,
  FileTextOutlined,
  FileWordOutlined,
  FileZipOutlined,
} from '@ant-design/icons';
import { theme } from 'antd';
import type React from 'react';

/** 按 mime 选择文件图标（非图片附件的"预览"列） */
const FileIcon: React.FC<{ mime: string }> = ({ mime }) => {
  const { token } = theme.useToken();
  const style = { fontSize: 32, color: token.colorTextSecondary };
  if (mime.includes('pdf')) return <FilePdfOutlined style={style} />;
  if (mime.includes('word')) return <FileWordOutlined style={style} />;
  if (mime.includes('sheet') || mime.includes('excel') || mime.includes('csv'))
    return <FileExcelOutlined style={style} />;
  if (mime.includes('presentation') || mime.includes('powerpoint'))
    return <FilePptOutlined style={style} />;
  if (mime.includes('zip')) return <FileZipOutlined style={style} />;
  if (mime.startsWith('text/')) return <FileTextOutlined style={style} />;
  return <FileOutlined style={style} />;
};

export default FileIcon;
