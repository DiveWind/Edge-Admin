<?php

namespace App\Models;

use App\Enums\LoginStatus;
use App\Models\Concerns\StoresDatesInAppTimezone;
use App\Services\Settings;
use Database\Factories\LoginLogFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Scope};
use Illuminate\Database\Eloquent\{Builder, MassPrunable, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'username', 'ip', 'user_agent', 'status', 'message'])]
class LoginLog extends Model
{
    /** @use HasFactory<LoginLogFactory> */
    use HasFactory, MassPrunable, StoresDatesInAppTimezone;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LoginStatus::class,
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
            ->when($filters['username'] ?? null, fn (Builder $q, string $value) => $q->whereLike('username', "%{$value}%"))
            ->when($filters['ip'] ?? null, fn (Builder $q, string $value) => $q->whereLike('ip', "%{$value}%"))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($filters['created_from'] ?? null, fn (Builder $q, string $value) => $q->whereDate('created_at', '>=', $this->asAppDateTime($value)))
            ->when($filters['created_to'] ?? null, fn (Builder $q, string $value) => $q->whereDate('created_at', '<=', $this->asAppDateTime($value)));
    }
}
