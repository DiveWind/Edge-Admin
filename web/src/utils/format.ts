const UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

/** 字节数 → 人类可读（1024 进制）：`1536` → `1.5 KB`；null / 非法值 → `-` */
export function formatBytes(
  bytes: number | null | undefined,
  digits = 1,
): string {
  if (bytes === null || bytes === undefined || !Number.isFinite(bytes)) {
    return '-';
  }
  if (bytes < 1024) return `${Math.max(0, Math.round(bytes))} B`;
  const exponent = Math.min(
    Math.floor(Math.log(bytes) / Math.log(1024)),
    UNITS.length - 1,
  );
  const value = bytes / 1024 ** exponent;
  // 去掉多余的 0：1.0 KB → 1 KB
  return `${Number(value.toFixed(digits))} ${UNITS[exponent]}`;
}
