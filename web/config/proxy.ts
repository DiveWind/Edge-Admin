/**
 * 开发代理配置，按 UMI_ENV 选择（默认 dev）。生产环境不经过这里。
 * @doc https://umijs.org/docs/guides/proxy
 */
const BACKEND = 'http://127.0.0.1:8000';

export default {
  dev: {
    '/api/': { target: BACKEND, changeOrigin: true },
    '/storage/': { target: BACKEND, changeOrigin: true },
  },
};
