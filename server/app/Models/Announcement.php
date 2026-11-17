<?php

namespace App\Models;

use App\Enums\{AnnouncementType, PublishStatus};
use App\Models\Concerns\StoresDatesInAppTimezone;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Scope};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'content', 'type'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, StoresDatesInAppTimezone;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function isPublished(): bool
    {
        return $this->status === PublishStatus::Published;
    }

    public function markAsPublished(): void
    {
        $this->forceFill([
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ])->save();
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
            ->when($filters['type'] ?? null, fn (Builder $q, string $value) => $q->where('type', $value))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value));
    }
}
