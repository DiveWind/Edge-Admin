import { LockOutlined, UserOutlined } from '@ant-design/icons';
import { LoginForm, ProFormText } from '@ant-design/pro-components';
import { Helmet, history, useModel } from '@umijs/max';
import { Alert, App } from 'antd';
import { createStyles } from 'antd-style';
import type React from 'react';
import { flushSync } from 'react-dom';
import { Footer, LangDropdown } from '@/components';
import { login } from '@/services/auth';
import { getSafeRedirect, setToken } from '@/utils/auth';
import { useT } from '@/utils/i18n';
import defaultSettings from '../../../../config/defaultSettings';

const useStyles = createStyles(({ token, css }) => ({
  container: css`
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background: ${token.colorBgLayout};
  `,
  lang: css`
    position: fixed;
    top: ${token.margin}px;
    right: ${token.margin}px;
  `,
  main: css`
    flex: 1;
    padding: ${token.paddingXL}px 0;
  `,
}));

const Login: React.FC = () => {
  const t = useT();
  const { styles } = useStyles();
  const { message } = App.useApp();
  const { initialState, setInitialState } = useModel('@@initialState');

  const handleSubmit = async (values: API.LoginParams) => {
    const { data } = await login(values);
    setToken(data.token);
    const currentUser = await initialState?.fetchUserInfo();
    if (!currentUser) return;
    // 同步提交 initialState，避免跳转后 layout 读到旧的未登录状态
    flushSync(() => {
      setInitialState((state) => (state ? { ...state, currentUser } : state));
    });
    message.success(t('pages.login.success'));
    const redirect = new URLSearchParams(window.location.search).get(
      'redirect',
    );
    history.replace(getSafeRedirect(redirect));
  };

  return (
    <div className={styles.container}>
      <Helmet>
        <title>{`${t('menu.login')} - ${defaultSettings.title}`}</title>
      </Helmet>
      <LangDropdown className={styles.lang} />
      <div className={styles.main}>
        <LoginForm<API.LoginParams>
          logo={<img alt="logo" src={defaultSettings.logo} />}
          title={defaultSettings.title}
          subTitle={t('pages.login.subTitle')}
          onFinish={async (values) => {
            // 失败提示（422 账号或密码错误、429 限流等）由全局 errorHandler 负责
            await handleSubmit(values).catch(() => undefined);
          }}
        >
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 24 }}
            title={t('pages.login.hint')}
          />
          <ProFormText
            name="username"
            fieldProps={{ size: 'large', prefix: <UserOutlined /> }}
            placeholder={t('pages.login.username.placeholder')}
            rules={[
              { required: true, message: t('pages.login.username.required') },
            ]}
          />
          <ProFormText.Password
            name="password"
            fieldProps={{ size: 'large', prefix: <LockOutlined /> }}
            placeholder={t('pages.login.password.placeholder')}
            rules={[
              { required: true, message: t('pages.login.password.required') },
            ]}
          />
        </LoginForm>
      </div>
      <Footer />
    </div>
  );
};

export default Login;
