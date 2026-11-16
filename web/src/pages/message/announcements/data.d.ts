export type AnnouncementType = 'notice' | 'announcement';

/** 公告（`/api/announcements`） */
export interface Announcement {
  id: API.Id;
  title: string;
  content: string;
  type: AnnouncementType;
  status: API.PublishStatus;
  published_at: string | null;
  created_by: API.Id;
  created_at: string;
  updated_at: string;
}

export interface AnnouncementFormValues {
  title: string;
  content: string;
  type: AnnouncementType;
}
