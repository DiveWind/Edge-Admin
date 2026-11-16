import { LockOutlined, UserOutlined } from '@ant-design/icons';
import type { ProFormInstance } from '@ant-design/pro-components';
import { LoginForm, ProFormText } from '@ant-design/pro-components';
import { Helmet, history, useModel } from '@umijs/max';
import { Alert, App, Flex } from 'antd';
import { createStyles } from 'antd-style';
import type React from 'react';
import { useRef } from 'react';
import { flushSync } from 'react-dom';
import { Footer, LangDropdown, ThemeSwitch } from '@/components';
import { login } from '@/services/auth';
import { getSafeRedirect, setToken } from '@/utils/auth';
import { submitForm } from '@/utils/form';
import { useT } from '@/utils/i18n';
import { DEFAULT_SITE_SETTINGS } from '@/utils/settings';
import defaultSettings from '../../../../config/defaultSettings';

const useStyles = createStyles(({ token, css }) => ({
  container: css`
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background: ${token.colorBgLayout};
  `,
  actions: css`
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
  const formRef = useRef<ProFormInstance<API.LoginParams>>(undefined);
  const site = initialState?.site ?? DEFAULT_SITE_SETTINGS;

  const handleSubmit = async (values: API.LoginParams) => {
    const { data } = await login(values);
    setToken(data.token);
    const [currentUser, menus = []] = await Promise.all([
      initialState?.fetchUserInfo(),
      initialState?.fetchMenus(),
    ]);
    if (!currentUser) return;
    // 同步提交 initialState（当前用户 + 该用户的菜单），避免跳转后 layout 读到旧的未登录状态 / 上一个账号的菜单
    flushSync(() => {
      setInitialState((state) =>
        state ? { ...state, currentUser, menus } : state,
      );
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
        <title>{`${t('menu.login')} - ${site.name}`}</title>
      </Helmet>
      <Flex className={styles.actions} gap={4}>
        <ThemeSwitch />
        <LangDropdown />
      </Flex>
      <div className={styles.main}>
        <LoginForm<API.LoginParams>
          logo={<img alt="logo" src={defaultSettings.logo} />}
          title={site.name}
          subTitle={t('pages.login.subTitle')}
          formRef={formRef}
          // 422（账号或密码错误、账号禁用）回填到 username 字段；提示由全局 errorHandler 负责
          onFinish={(values) =>
            submitForm(formRef.current, () => handleSubmit(values))
          }
        >
          {/* 系统参数 site.login_notice，为空不展示 */}
          {site.loginNotice && (
            <Alert
              type="warning"
              showIcon
              style={{ marginBottom: 16 }}
              title={site.loginNotice}
            />
          )}
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
