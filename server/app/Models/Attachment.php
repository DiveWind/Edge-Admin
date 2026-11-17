<?php

namespace App\Models;

use App\Models\Concerns\StoresDatesInAppTimezone;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Scope};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 附件：每次 POST /api/uploads 落一条记录，删除时连同物理文件一起删除。
 */
#[Fillable(['disk', 'path', 'name', 'mime', 'size', 'uploaded_by'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, StoresDatesInAppTimezone;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    /**
     * 可访问的 URL（public 磁盘依赖 storage:link 与 APP_URL）。
     *
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk($this->disk)->url($this->path));
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['name'] ?? null, fn (Builder $q, string $value) => $q->whereLike('name', "%{$value}%"))
            ->when($filters['mime'] ?? null, fn (Builder $q, string $value) => $q->whereLike('mime', "%{$value}%"))
            ->when($filters['uploaded_by'] ?? null, fn (Builder $q, int|string $value) => $q->where('uploaded_by', $value))
            ->when($filters['created_from'] ?? null, fn (Builder $q, string $value) => $q->whereDate('created_at', '>=', $this->asAppDateTime($value)))
            ->when($filters['created_to'] ?? null, fn (Builder $q, string $value) => $q->whereDate('created_at', '<=', $this->asAppDateTime($value)));
    }
}
