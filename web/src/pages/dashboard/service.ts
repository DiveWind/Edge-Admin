import { request } from '@umijs/max';
import type { DashboardData } from './data';

export function getDashboard() {
  return request<API.Resource<DashboardData>>('/api/dashboard');
}
