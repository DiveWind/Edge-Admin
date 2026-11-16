/** 系统信息（`GET /api/system-info`）；无法获取的指标为 null */
export interface SystemInfo {
  app: {
    name: string;
    env: string;
    debug: boolean;
    version: string | null;
    locale: string;
    timezone: string;
  };
  runtime: {
    php_version: string;
    laravel_version: string;
    os: string;
    sapi: string;
    memory_limit: string | null;
    upload_max_filesize: string | null;
    post_max_size: string | null;
    server_time: string;
  };
  drivers: {
    database: { connection: string; driver: string; version: string | null };
    cache: string;
    queue: string;
    session: string;
    filesystem: string;
    upload_disk: string;
  };
  storage: {
    disk_total_bytes: number | null;
    disk_free_bytes: number | null;
    database_bytes: number | null;
    attachments_bytes: number | null;
  };
  metrics: {
    users: number;
    online_sessions: number;
    attachments: number;
    failed_jobs: number;
  };
}
