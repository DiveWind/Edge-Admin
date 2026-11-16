import { join } from 'node:path';
import { defineConfig } from 'vitest/config';

/**
 * 单元测试只覆盖纯逻辑（权限、请求参数转换等），运行在 node 环境，不依赖浏览器。
 */
export default defineConfig({
  resolve: {
    alias: {
      '@': join(__dirname, 'src'),
      '@@': join(__dirname, 'src', '.umi'),
    },
  },
  test: {
    environment: 'node',
    include: ['src/**/*.test.ts'],
    exclude: ['node_modules', 'dist', 'src/.umi*/**'],
  },
});
