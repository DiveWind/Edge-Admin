<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\DeleteExport;
use App\Core\System\Models\{Export, User};
use App\Http\Admin\System\Requests\ExportIndexRequest;
use App\Http\Admin\System\Resources\ExportResource;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 我的导出（设计文档 §6.10）：列表、详情、删除。发起导出在各列表的导出入口（如 UserExportController）。
 *
 * 列表只需登录（LOGIN_ONLY_ROUTES），只查本人的导出；详情、删除由 ExportPolicy 判定只能访问本人的（403）。
 * 文件是绑定在导出记录上的附件，下载地址（签名）由本接口签发；写入调用 System 模块的 Action。
 */
class ExportController
{
    /**
     * 列表（只有本人的，预加载 file）：筛选 name、status、format（精确）、created_at（区间 `起,止`）；排序 id、created_at，默认 `-id`。
     * 不在白名单内的参数返回 422（§6.3）。
     */
    public function index(ExportIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $exports = QueryBuilder::for(Export::query()->ownedBy($actor)->with('file'), $request)
            ->allowedFilters(
                AllowedFilter::exact('name', 'exporter'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('format'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id', 'created_at')
            ->defaultSort('-id')
            ->paginate($request->pageSize());

        return ExportResource::collection($exports);
    }

    /**
     * 详情，含 file（签名的下载地址）。
     */
    #[Authorize('view', 'export')]
    public function show(Export $export): ExportResource
    {
        return new ExportResource($export->load('file'));
    }

    /**
     * 删除（连同文件）；排队、进行中的即取消。
     */
    #[Authorize('delete', 'export')]
    public function destroy(Export $export, DeleteExport $action): void
    {
        $action->handle($export);
    }
}
