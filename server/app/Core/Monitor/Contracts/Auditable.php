<?php

namespace App\Core\Monitor\Contracts;

/**
 * 可审计的模型（Core 的扩展点“日志”，设计文档 §5.5）：模型只负责提供审计字段的快照，写入、比较差异、大字段摘要由 Monitor 的
 * LogAudit 统一处理。
 *
 * ```php
 * class Article extends Model implements Auditable
 * {
 *     public function auditSnapshot(): array
 *     {
 *         return ['title' => $this->title, 'status' => $this->status->value, 'content' => $this->content];
 *     }
 *
 *     public function auditDigestedFields(): array
 *     {
 *         return ['content'];
 *     }
 * }
 * ```
 */
interface Auditable
{
    /**
     * 审计的字段（已保存的值）：字段 => 标量、null 或由标量组成的数组（日期用 ISO 8601，枚举用 value，关联用 id）。
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array;

    /**
     * 只记摘要、不存原文的大字段（如富文本正文）：日志中记为 `{ sha256, length }`，能看出是否变化，并可与某个版本核对。
     *
     * @return list<string>
     */
    public function auditDigestedFields(): array;
}
