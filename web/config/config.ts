// https://umijs.org/config/
import { defineConfig } from '@umijs/max';
import defaultSettings from './defaultSettings';
import proxy from './proxy';
import routes from './routes';

const { UMI_ENV = 'dev' } = process.env;

/**
 * 部署路径，部署在非根目录时修改
 * @doc https://umijs.org/docs/api/config#publicpath
 */
const PUBLIC_PATH = '/';

export default defineConfig({
  hash: true,
  publicPath: PUBLIC_PATH,
  routes,
  /**
   * 开发代理，仅 `max dev` 生效；生产环境由网关 / Nginx 负责转发 `/api`、`/storage`
   * @doc https://umijs.org/docs/guides/proxy
   */
  proxy: proxy[UMI_ENV as keyof typeof proxy],
  fastRefresh: true,
  routePrefetch: {},
  /** 资源清单，配合 routePrefetch 使用（不是 PWA manifest） */
  manifest: {},
  /** 运行时数据一律来自后端，不使用 mock */
  mock: false,
  title: defaultSettings.title,
  favicons: [`${PUBLIC_PATH}logo.svg`],
  links: [{ rel: 'manifest', href: `${PUBLIC_PATH}manifest.json` }],
  headScripts: [
    // 首屏 loading，避免白屏
    { src: `${PUBLIC_PATH}scripts/loading.js`, async: true },
  ],
  ignoreMomentLocale: true,

  //============== Umi Max 插件 ===============
  model: {},
  initialState: {},
  /** @doc https://umijs.org/docs/max/layout-menu */
  layout: {
    locale: true,
    ...defaultSettings,
  },
  moment2dayjs: {
    preset: 'antd',
    plugins: ['duration', 'relativeTime'],
  },
  /** @doc https://umijs.org/docs/max/i18n */
  locale: {
    default: 'zh-CN',
    antd: true,
    // 不跟随浏览器语言，默认中文；用户切换后由插件记忆
    baseNavigator: false,
  },
  /** @doc https://umijs.org/docs/max/antd */
  antd: {
    appConfig: {},
    configProvider: {
      variant: 'filled',
    },
  },
  /** @doc https://umijs.org/docs/max/request */
  request: {},
  /** @doc https://umijs.org/docs/max/react-query */
  reactQuery: {},
  /** @doc https://umijs.org/docs/max/access */
  access: {},
  tailwindcss: {},
  utoopack: {
    devServer: {
      dynamicHmrChunkLists: true,
      lazyDynamicImports: true,
    },
  },
});
