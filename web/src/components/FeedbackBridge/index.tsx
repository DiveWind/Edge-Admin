import { App } from 'antd';
import type { MessageInstance } from 'antd/es/message/interface';
import type React from 'react';
import { useEffect } from 'react';

let messageApi: MessageInstance | undefined;

/**
 * 组件外（请求层）使用的 message 实例。
 * antd 的静态 `message.xxx` 拿不到 ConfigProvider 的主题 / 语言，
 * 因此由 FeedbackBridge 在 `<App>` 内把 `App.useApp()` 的实例暴露出来。
 */
export const getMessageApi = (): MessageInstance | undefined => messageApi;

/** 挂在 antd `<App>` 内部（见 app.tsx 的 innerProvider），不渲染任何内容 */
const FeedbackBridge: React.FC = () => {
  const { message } = App.useApp();
  useEffect(() => {
    messageApi = message;
    return () => {
      messageApi = undefined;
    };
  }, [message]);
  return null;
};

export default FeedbackBridge;
