<?php

namespace App\Core\System\Actions;

use App\Core\System\Contracts\Exporter;
use App\Core\System\Enums\ExportStatus;
use App\Core\System\Events\ExportFinished;
use App\Core\System\Models\{Attachment, Export, User};
use App\Core\System\Services\{ExportWriter, ExporterRegistry};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Http\File;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\{LazyCollection, Str};
use Illuminate\Support\Traits\Localizable;
use RuntimeException;
use Throwable;

/**
 * 生成导出文件（队列任务 RunExport 调用，设计文档 §6.10）。
 *
 * 1. 锁行复核：记录已删除（被取消）或已不是排队中 → 直接结束（重复投递时幂等）；否则标记进行中；
 * 2. 复核发起人：账号启用、仍有导出类的权限码（按执行时的授权）；否则标记失败（FailExport），不写出任何数据；
 * 3. 按发起时的语言（exports.locale）翻译表头与显示名（withLocale()，执行后恢复，常驻内存安全），用 ExportWriter 流式写入本机临时文件：
 *    查询用 lazy(config('core.exports.chunk_size')) 分块读取，不一次读入全部数据；最后追加主键排序，排序列有重复值时分块也稳定；
 * 4. 文件存到附件盘（core.attachments.disk）的 `exports/<年>/<月>/<日>/<40 位随机串>.<扩展名>`；在一个事务中锁行复核记录仍在，
 *    登记附件（绑定到导出记录的 `file` 用途，created_by 为发起人），标记已完成、row_count、finished_at；
 *    记录在执行期间被删除 → 删除已存的文件。临时文件无论成败都删除；
 * 5. 派发 ExportFinished（Message 据此通知发起人）。
 *
 * 写出过程中的异常照常抛出：RunExport 的 failed() 调用 FailExport。
 */
class GenerateExport
{
    use Localizable;

    public function __construct(
        private readonly ExporterRegistry $exporters,
        private readonly ExportWriter $writer,
        private readonly FailExport $fail,
    ) {}

    /**
     * Execute the action.
     *
     * @throws Throwable 写出或保存失败
     */
    public function handle(int $exportId): void
    {
        $export = $this->start($exportId);

        if (! $export instanceof Export) {
            return;
        }

        $exporter = $this->exporters->get($export->exporter);
        $user = $export->user()->first();

        if ($user === null || ! $user->isEnabled() || ! $user->can($exporter->permission())) {
            $this->fail->handle($export->id);

            return;
        }

        $temporary = sys_get_temp_dir().'/core-export-'.Str::random(40);

        try {
            $rows = $this->withLocale($export->locale, fn (): int => $this->writer->write(
                $temporary,
                $export->format,
                array_values(array_map($this->translate(...), $exporter->columns())),
                $this->rows($exporter, $user, $export),
            ));
            $title = $this->withLocale($export->locale, fn (): string => $this->translate($exporter->title()));

            $completed = $this->complete($export, $user, $temporary, $title, $rows);
        } finally {
            \Illuminate\Support\Facades\File::delete($temporary);
        }

        if ($completed instanceof Export) {
            event(new ExportFinished($completed));
        }
    }

    /**
     * 按当前语言翻译后端文案（JSON 语言包，找不到时为原文）。
     */
    private function translate(string $text): string
    {
        $translated = __($text);

        return is_string($translated) ? $translated : $text;
    }

    /**
     * 锁行复核仍是排队中，标记进行中；记录不存在或已处理时为 null。
     */
    private function start(int $exportId): ?Export
    {
        return DB::transaction(function () use ($exportId): ?Export {
            $export = Export::query()->lockForUpdate()->find($exportId);

            if ($export === null || $export->status !== ExportStatus::Queued) {
                return null;
            }

            $export->status = ExportStatus::Running;
            $export->started_at = now();
            $export->save();

            return $export;
        });
    }

    /**
     * 按 columns() 的顺序取出每一行的值（分块读取）。
     *
     * @param  Exporter<Model>  $exporter
     * @return LazyCollection<int, list<string|int|float|bool|null>>
     */
    private function rows(Exporter $exporter, User $user, Export $export): LazyCollection
    {
        $query = $exporter->query($user, $export->parameters());
        $keys = array_keys($exporter->columns());

        return $this->withStableOrder($query)
            ->lazy(max(1, config()->integer('core.exports.chunk_size')))
            ->map(function (Model $row) use ($exporter, $keys): array {
                $values = $exporter->map($row);

                return array_map(fn (string $key): string|int|float|bool|null => $values[$key] ?? null, $keys);
            });
    }

    /**
     * 在已有排序之后追加主键排序：分块（offset）读取时，排序列有重复值的行不会重复或遗漏。
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function withStableOrder(Builder $query): Builder
    {
        return $query->orderBy($query->getModel()->getQualifiedKeyName());
    }

    /**
     * 保存文件、登记附件并标记已完成；记录已被删除时删除文件并返回 null。
     *
     * @throws Throwable
     */
    private function complete(Export $export, User $user, string $temporary, string $title, int $rows): ?Export
    {
        $disk = config()->string('core.attachments.disk');
        $extension = $export->format->value;
        $path = Storage::disk($disk)->putFileAs(Export::DIRECTORY.'/'.now()->format('Y/m/d'), new File($temporary), Str::random(40).'.'.$extension);

        if (! is_string($path)) {
            throw new RuntimeException("导出文件保存到存储盘 [{$disk}] 失败。");
        }

        try {
            $completed = DB::transaction(function () use ($export, $user, $temporary, $title, $rows, $disk, $path, $extension): ?Export {
                $locked = Export::query()->lockForUpdate()->find($export->id);

                if ($locked === null) {
                    return null;
                }

                $attachment = new Attachment([
                    'disk' => $disk,
                    'path' => $path,
                    'name' => mb_substr(str_replace(['/', '\\'], '-', $title), 0, 200).'-'.now()->format('Ymd-His').'.'.$extension,
                    'mime_type' => $export->format->mimeType(),
                    'extension' => $extension,
                    'size' => (int) filesize($temporary),
                    'sha256' => (string) hash_file('sha256', $temporary),
                ]);
                $attachment->created_by = $user->id;
                $attachment->collection = Export::FILE_COLLECTION;
                $attachment->attachable()->associate($locked);
                $attachment->save();

                $locked->status = ExportStatus::Completed;
                $locked->row_count = max(0, $rows);
                $locked->finished_at = now();
                $locked->save();

                return $locked;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        if ($completed === null) {
            Storage::disk($disk)->delete($path);
        }

        return $completed;
    }
}
