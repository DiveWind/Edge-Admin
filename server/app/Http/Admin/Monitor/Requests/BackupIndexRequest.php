<?php

namespace App\Http\Admin\Monitor\Requests;

use App\Core\Monitor\Queries\BackupFiles;
use App\Http\Admin\Requests\ListRequest;

/**
 * 备份列表的查询参数（GET /monitor/backups，设计文档 §6.3、§6.10）：page、pageSize 由 ListRequest 校验。
 *
 * 备份不是数据库记录，不经过 query-builder，白名单在这里：筛选只有 disk（必须是配置中的备份盘），排序只有 created_at、size（`-` 降序），
 * 其他筛选（`filter` 只允许 disk 键）、排序 → 422。
 */
class BackupIndexRequest extends ListRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $sorts = [...BackupFiles::SORTS, ...array_map(fn (string $sort): string => "-{$sort}", BackupFiles::SORTS)];

        return [
            ...parent::rules(),
            'filter' => ['sometimes', 'array:disk'],
            'filter.disk' => ['sometimes', 'string', 'in:'.implode(',', BackupFiles::disks())],
            'sort' => ['sometimes', 'string', 'in:'.implode(',', $sorts)],
        ];
    }

    /**
     * 筛选的备份盘（未筛选时为 null）。
     */
    public function disk(): ?string
    {
        $disk = $this->input('filter.disk');

        return is_string($disk) ? $disk : null;
    }

    /**
     * 排序（默认 `-created_at`）。
     */
    public function sort(): string
    {
        return $this->string('sort', '-created_at')->toString();
    }
}
