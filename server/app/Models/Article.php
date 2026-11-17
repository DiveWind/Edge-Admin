<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\{HasDataScope, StoresDatesInAppTimezone};
use App\Observers\ArticleObserver;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, ObservedBy, Scope};
use Illuminate\Database\Eloquent\{Builder, Model, SoftDeletes};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 业务对接示例：接入数据权限（HasDataScope），作者 / 部门在创建时自动填充。
 */
#[ObservedBy(ArticleObserver::class)]
#[Fillable(['category_id', 'title', 'summary', 'cover', 'content', 'status', 'source'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasDataScope, HasFactory, SoftDeletes, StoresDatesInAppTimezone;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'original',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'department_id' => 'integer',
            'created_by' => 'integer',
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ArticleCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['title'] ?? null, fn (Builder $q, string $value) => $q->whereLike('title', "%{$value}%"))
            ->when($filters['category_id'] ?? null, fn (Builder $q, int|string $value) => $q->where('category_id', $value))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($filters['source'] ?? null, fn (Builder $q, string $value) => $q->where('source', $value));
    }
}
