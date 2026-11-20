<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\{ExportFormat, ExportStatus};
use App\Core\System\Jobs\RunExport;
use App\Core\System\Models\{Export, User};
use App\Core\System\Services\ExporterRegistry;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\{App, DB};
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\QueryBuilder\Exceptions\InvalidQuery;

/**
 * 发起导出（POST /<列表路径>/export，设计文档 §6.10）：创建导出记录并入队 RunExport，立即返回。
 *
 * - 同步构造一次查询（不执行）：筛选、排序不在列表的白名单内 → InvalidQuery，时间区间格式错误 → ValidationException（都是 422），
 *   与列表的行为一致，不会等到队列中才失败；
 * - 并发上限：同一用户排队与进行中的导出不超过 config('core.exports.max_active')，超过 → 422（message）。
 *   检查与写入在一个事务中，先锁住操作者的用户行，同一用户的并发请求串行化；
 * - 记录 locale（当前请求的语言，生成文件时按它翻译）与 params（`{ filter, sort }`）；
 * - RunExport 实现 ShouldQueueAfterCommit：事务提交后才入队。
 *
 * 权限码由导出入口的 #[Authorize] 判定（403），执行时 GenerateExport 再复核。
 */
class CreateExport
{
    public function __construct(private readonly ExporterRegistry $exporters) {}

    /**
     * Execute the action.
     *
     * @param  string  $exporter  导出类的对外名称（ExporterRegistry）
     * @param  array<string, mixed>  $parameters  列表参数 `{ filter, sort }`
     *
     * @throws InvalidQuery
     * @throws ValidationException
     * @throws BusinessRuleViolation 并发上限
     * @throws InvalidArgumentException 导出类未登记（开发期错误）
     */
    public function handle(User $actor, string $exporter, ExportFormat $format, array $parameters): Export
    {
        $this->exporters->get($exporter)->query($actor, $parameters);

        return DB::transaction(function () use ($actor, $exporter, $format, $parameters): Export {
            User::query()->whereKey($actor->id)->lockForUpdate()->first();

            if (Export::query()->ownedBy($actor)->active()->count() >= config()->integer('core.exports.max_active')) {
                throw new BusinessRuleViolation(__('You can have at most :count exports in progress. Please wait for them to finish.', [
                    'count' => config()->integer('core.exports.max_active'),
                ]));
            }

            $export = new Export([
                'exporter' => $exporter,
                'format' => $format,
                'params' => $parameters,
                'locale' => App::getLocale(),
            ]);
            $export->user_id = $actor->id;
            $export->status = ExportStatus::Queued;
            $export->save();

            dispatch(new RunExport($export->id));

            return $export;
        });
    }
}
