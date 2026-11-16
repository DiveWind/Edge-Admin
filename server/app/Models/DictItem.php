<?php

namespace App\Models;

use App\Enums\Status;
use App\Services\Dictionary;
use Database\Factories\DictItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 数据字典项：label 用于展示，value 为存储值（同一类型下唯一）。
 */
#[Fillable(['dict_type_id', 'label', 'value', 'color', 'sort', 'status', 'remark'])]
class DictItem extends Model
{
    /** @use HasFactory<DictItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dict_type_id' => 'integer',
            'sort' => 'integer',
            'status' => Status::class,
        ];
    }

    /**
     * 字典变更时清除字典缓存。
     */
    protected static function booted(): void
    {
        static::saved(fn () => app(Dictionary::class)->flush());
        static::deleted(fn () => app(Dictionary::class)->flush());
    }

    /**
     * @return BelongsTo<DictType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DictType::class, 'dict_type_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', Status::Active);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['dict_type_id'] ?? null, fn (Builder $q, int|string $value) => $q->where('dict_type_id', $value))
            ->when($filters['label'] ?? null, fn (Builder $q, string $value) => $q->whereLike('label', "%{$value}%"))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value));
    }
}
