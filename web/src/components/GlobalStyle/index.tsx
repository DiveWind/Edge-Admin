import { createGlobalStyle } from 'antd-style';

/**
 * 依赖主题 token 的全局样式（放在 antd ConfigProvider 内，随明 / 暗主题切换）。
 * 页面背景必须给到 body：内容超过一屏时滚动区域露出的是 body 背景，写死或缺省都会在暗色下露白。
 */
const GlobalStyle = createGlobalStyle`
  body {
    background: ${({ theme }) => theme.colorBgLayout};
  }
`;

export default GlobalStyle;
