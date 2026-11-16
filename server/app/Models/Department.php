<?php

namespace App\Models;

use App\Enums\Status;
use App\Models\Collections\TreeCollection;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[CollectedBy(TreeCollection::class)]
#[Fillable(['parent_id', 'name', 'leader_id', 'sort', 'status'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort' => 'integer',
            'status' => Status::class,
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * 指定部门及其全部下级部门的 id（在 PHP 侧遍历，避免方言 SQL）。
     *
     * @return list<int>
     */
    public static function descendantAndSelfIds(int $id): array
    {
        $childrenByParent = static::query()
            ->get(['id', 'parent_id'])
            ->groupBy(fn (Department $department): int => (int) $department->parent_id);

        $ids = [];
        $pending = [$id];

        while ($pending !== []) {
            $current = array_shift($pending);

            if (in_array($current, $ids, true)) {
                continue;
            }

            $ids[] = $current;

            foreach ($childrenByParent->get($current, []) as $child) {
                $pending[] = $child->id;
            }
        }

        return $ids;
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
            ->when($filters['name'] ?? null, fn (Builder $q, string $value) => $q->whereLike('name', "%{$value}%"))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value));
    }
}
