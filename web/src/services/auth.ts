import { request } from '@umijs/max';

/** 认证与个人中心（契约 §5.1） */

export function login(body: API.LoginParams) {
  return request<API.Resource<API.LoginResult>>('/api/auth/login', {
    method: 'POST',
    data: body,
  });
}

export function logout(options?: { skipErrorHandler?: boolean }) {
  return request<void>('/api/auth/logout', { method: 'POST', ...options });
}

export function getCurrentUser(options?: { skipErrorHandler?: boolean }) {
  return request<API.Resource<API.CurrentUser>>('/api/auth/me', {
    method: 'GET',
    ...options,
  });
}

export function updateProfile(body: API.ProfileParams) {
  return request<API.Resource<API.CurrentUser>>('/api/auth/profile', {
    method: 'PUT',
    data: body,
  });
}

export function updatePassword(body: API.PasswordParams) {
  return request<void>('/api/auth/password', { method: 'PUT', data: body });
}
