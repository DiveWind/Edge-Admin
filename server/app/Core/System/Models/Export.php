<?php

namespace App\Core\System\Models;

use App\Core\System\Contracts\PrivateAttachable;
use App\Core\System\Enums\{ExportFormat, ExportStatus};
use App\Core\System\Models\Concerns\HasAttachments;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Carbon\CarbonInterface;
use Database\Factories\Core\System\Models\ExportFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Scope};
use Illuminate\Database\Eloquent\{Builder, Model, Prunable};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphOne};

/**
 * 异步导出的任务（多态别名 `export`，见 SystemServiceProvider），设计文档 §6.10：
 *
 * - user：发起人，只有他能查看、下载、删除（ExportPolicy）；
 * - exporter：导出类的对外名称（ExporterRegistry），params：列表参数 `{ filter, sort }`，在队列中按它重新构造查询；
 * - locale：发起时的语言，生成文件时按它翻译表头与显示名；
 * - status：ExportStatus（queued → running → completed / failed），row_count：完成时写出的行数；
 * - 生成的文件是绑定在 `file` 用途上的附件（HasAttachments，签名下载，§6.8）；实现 PrivateAttachable：附件管理员也不能访问；
 * - 创建超过 config('core.system.export.ttl_hours') 小时的导出（不论状态）由 `model:prune` 逐条删除（Prunable，连同文件）。
 *
 * 写入只在 System 模块的 Action 中进行（CreateExport、GenerateExport、FailExport、DeleteExport，设计文档 §5.2）。
 *
 * @property int $id
 */
#[Fillable(['exporter', 'format', 'params', 'locale'])]
class Export extends Model implements PrivateAttachable
{
    /** @use HasFactory<ExportFactory> */
    use HasAttachments, HasFactory, Prunable, UsesAppTimezone;

    /**
     * 文件的附件用途名。
     */
    public const string FILE_COLLECTION = 'file';

    /**
     * 文件的存放目录（附件盘内）。
     */
    public const string DIRECTORY = 'exports';

    /**
     * 发起人。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 生成的文件（完成前为 null）。
     *
     * @return MorphOne<Attachment, $this>
     */
    public function file(): MorphOne
    {
        return $this->attachmentIn(self::FILE_COLLECTION);
    }

    /**
     * 是否属于 $user（发起人）。
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * 到期清理的时间（创建时间 + ttl 小时）。
     */
    public function expiresAt(): ?CarbonInterface
    {
        return $this->created_at?->addHours(config()->integer('core.system.export.ttl_hours'));
    }

    /**
     * 列表参数 `{ filter, sort }`。
     *
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return array_filter($this->params, is_string(...), ARRAY_FILTER_USE_KEY);
    }

    /**
     * 只保留 $user 发起的导出。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * 只保留排队中与进行中的导出（计入并发上限）。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', ExportStatus::active());
    }

    /**
     * 到期清理（model:prune）：创建于 ttl 小时之前的导出，不论状态（卡在排队中的也清理）。
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subHours(config()->integer('core.system.export.ttl_hours')));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'format' => ExportFormat::class,
            'status' => ExportStatus::class,
            'params' => 'array',
            'row_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
