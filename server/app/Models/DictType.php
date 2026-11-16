<?php

namespace App\Models;

use App\Enums\Status;
use App\Services\Dictionary;
use Database\Factories\DictTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 数据字典类型，code 为后端与前端引用字典的唯一标识（如 article_source）。
 */
#[Fillable(['name', 'code', 'status', 'remark'])]
class DictType extends Model
{
    /** @use HasFactory<DictTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return HasMany<DictItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DictItem::class);
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
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['name'] ?? null, fn (Builder $q, string $value) => $q->whereLike('name', "%{$value}%"))
            ->when($filters['code'] ?? null, fn (Builder $q, string $value) => $q->whereLike('code', "%{$value}%"))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value));
    }
}
