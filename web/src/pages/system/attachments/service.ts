import { request } from '@umijs/max';

/** 附件列表（项含 uploader），过滤 name mime(模糊) uploaded_by created_from/to */
export function queryAttachments(params: API.ListQuery) {
  return request<API.Paginated<API.Attachment>>('/api/attachments', {
    params,
  });
}

/** 同时删除物理文件 */
export function deleteAttachment(id: API.Id) {
  return request<void>(`/api/attachments/${id}`, { method: 'DELETE' });
}
