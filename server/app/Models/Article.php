<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasDataScope;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 业务对接示例：接入数据权限（HasDataScope），作者 / 部门在创建时自动填充。
 */
#[Fillable(['category_id', 'title', 'summary', 'cover', 'content', 'status'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasDataScope, HasFactory, SoftDeletes;

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

    protected static function booted(): void
    {
        static::saving(function (Article $article): void {
            if ($article->isDirty('status') && $article->status === PublishStatus::Published) {
                $article->published_at ??= now();
            }
        });
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
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value));
    }
}
