<?php

namespace App\Http\Admin\Content\Controllers;

use App\Core\System\Actions\CreateExport;
use App\Core\System\Models\User;
use App\Http\Admin\Content\Requests\ArticleExportRequest;
use App\Http\Admin\System\Resources\ExportResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 导出文章列表（POST /content/articles/export，设计文档 §6.10、§6.11）：创建导出任务并入队，返回导出记录（201）。
 *
 * 权限码 content.article.export（Gate::before，执行时再复核）；筛选与排序与列表相同（ArticleList 的白名单、数据范围与 trashed 的授权），
 * 不合法 422、查看已删除没有授权 403；同时进行的导出超过上限 422（CreateExport）；路由另经过 admin-exports 限流。
 * 文件在“我的导出”（/exports）中下载。
 */
class ArticleExportController
{
    #[Authorize('content.article.export')]
    public function __invoke(ArticleExportRequest $request, CreateExport $action, #[CurrentUser] User $actor): ExportResource
    {
        return new ExportResource($action->handle($actor, 'content.article', $request->exportFormat(), $request->listParameters()));
    }
}
