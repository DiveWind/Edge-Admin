import { request } from '@umijs/max';
import type { SystemInfo } from './data';

export function getSystemInfo() {
  return request<API.Resource<SystemInfo>>('/api/system-info');
}
