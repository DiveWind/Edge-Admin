import { defineConfig } from '@vben/vite-config';

export default defineConfig(async () => {
  return {
    application: {},
    vite: {
      server: {
        proxy: {
          // 开发时经代理与 Laravel 后端（php artisan serve --port=8000）同源，会话 Cookie 与 CSRF 正常工作。
          // 路径不改写：/api/admin/... 原样转发；保留 Host（不设 changeOrigin），Sanctum 按 Referer / Origin 识别前端（localhost:5999）
          '/api': {
            target: 'http://localhost:8000',
          },
        },
      },
    },
  };
});
