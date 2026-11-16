<?php

namespace App\Models;

use App\Services\Settings;
use Database\Factories\OperationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'method', 'path', 'route_name', 'summary', 'ip', 'user_agent', 'status_code', 'duration_ms', 'payload'])]
class OperationLog extends Model
{
    /** @use HasFactory<OperationLogFactory> */
    use HasFactory, MassPrunable;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * 超过保留天数（系统参数 log.retention_days，默认 config('edge.logs.retention_days')）的记录由 `model:prune` 清理。
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(app(Settings::class)->integer('log.retention_days', config()->integer('edge.logs.retention_days'))));
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['username'] ?? null, fn (Builder $q, string $value) => $q->whereHas(
                'user',
                fn (Builder $user) => $user->withTrashed()->whereLike('username', "%{$value}%"),
            ))
            ->when($filters['method'] ?? null, fn (Builder $q, string $value) => $q->where('method', strtoupper($value)))
            ->when($filters['path'] ?? null, fn (Builder $q, string $value) => $q->whereLike('path', "%{$value}%"))
            ->when($filters['status_code'] ?? null, fn (Builder $q, int|string $value) => $q->where('status_code', $value))
            ->when($filters['created_from'] ?? null, fn (Builder $q, string $value) => $q->whereDate('created_at', '>=', $value))
            ->when($filters['created_to'] ?? null, fn (Builder $q, string $value) => $q->whereDate('created_at', '<=', $value));
    }
}
