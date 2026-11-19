<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Queries\BackupFiles;
use App\Http\Admin\Monitor\Requests\BackupIndexRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 备份列表（GET /monitor/backups，设计文档 §6.10）：各备份盘上的备份文件 `{ items, total }`，每项 `{ disk, path, size, created_at }`。
 *
 * 没有模型：直接声明菜单节点的权限码 monitor.backup，由 Gate::before 判定（§8.1）；不按数据范围（备份属于系统本身）。
 * **不提供下载**：备份含整个数据库与全部附件，需要时从备份盘直接取（§6.10）。
 */
class BackupController
{
    /**
     * @return LengthAwarePaginator<int, array{disk: string, path: string, size: int, created_at: string}>
     */
    #[Authorize('monitor.backup')]
    public function index(BackupIndexRequest $request, BackupFiles $query): LengthAwarePaginator
    {
        return $query->paginate($request->disk(), $request->sort(), $request->page(), $request->pageSize());
    }
}
