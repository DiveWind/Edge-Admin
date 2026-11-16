import { request } from '@umijs/max';

/**
 * 上传文件到 public 磁盘（默认 ≤2MB，类型白名单见契约 §5.6），同时登记为附件。
 * 返回完整的 Attachment（`url` 可直接访问）。
 */
export function uploadFile(file: Blob) {
  const data = new FormData();
  data.append('file', file);
  return request<API.Resource<API.Attachment>>('/api/uploads', {
    method: 'POST',
    data,
  });
}
