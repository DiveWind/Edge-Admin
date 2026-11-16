import { request } from '@umijs/max';

/** 上传图片到 public 磁盘（≤2MB），返回可直接访问的 url 与存储 path */
export function uploadImage(file: Blob) {
  const data = new FormData();
  data.append('file', file);
  return request<API.Resource<API.UploadResult>>('/api/uploads', {
    method: 'POST',
    data,
  });
}
