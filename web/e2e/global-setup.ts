import { mkdirSync, writeFileSync } from 'node:fs';
import { request } from '@playwright/test';
import { AUTH_DIR, ROLE_USERS, storageStatePath } from './support';

/**
 * 每个角色通过 API 登录一次，把 token 写成 storageState（localStorage），用例直接复用登录态。
 * 登录接口按"用户名 + IP"每分钟限流，避免在每个用例里重复登录。
 */
export default async function globalSetup() {
  const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:8001';
  const api = await request.newContext({ baseURL });
  mkdirSync(AUTH_DIR, { recursive: true });

  for (const username of ROLE_USERS) {
    const response = await api.post('/api/auth/login', {
      headers: { Accept: 'application/json' },
      data: { username, password: 'password' },
    });
    if (!response.ok()) {
      throw new Error(
        `登录 ${username} 失败（HTTP ${response.status()}）：请确认后端已启动并执行过 migrate:fresh --seed。${await response.text()}`,
      );
    }
    const { data } = (await response.json()) as { data: { token: string } };
    writeFileSync(
      storageStatePath(username),
      JSON.stringify({
        cookies: [],
        origins: [
          {
            origin: new URL(baseURL).origin,
            localStorage: [
              { name: 'edge_admin_token', value: data.token },
              { name: 'umi_locale', value: 'zh-CN' },
            ],
          },
        ],
      }),
    );
  }
  await api.dispose();
}
