import { DefaultFooter } from '@ant-design/pro-components';
import type React from 'react';
import defaultSettings from '../../../config/defaultSettings';

const Footer: React.FC = () => (
  <DefaultFooter
    style={{ background: 'none' }}
    copyright={`${new Date().getFullYear()} ${defaultSettings.title}`}
    links={false}
  />
);

export default Footer;
