<?php

namespace App\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use App\Models\Concerns\StoresDatesInAppTimezone;
use App\Services\Settings;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 系统参数。value 按 type 读写（见 SettingValue）；is_system 为内置参数（不可删、不可改 key / type），
 * is_public 的参数可通过 GET /api/options/settings 免登录读取。
 *
 * 注意：value 的转换依赖 type，批量赋值时 type 须排在 value 之前（Form Request 的规则顺序保证了这一点）。
 */
#[Fillable(['group', 'key', 'name', 'type', 'value', 'is_public', 'remark'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, StoresDatesInAppTimezone;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'string',
        'is_public' => false,
        'is_system' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
            'value' => SettingValue::class,
            'is_public' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * 参数变更时清除参数缓存。
     */
    protected static function booted(): void
    {
        static::saved(fn () => app(Settings::class)->flush());
        static::deleted(fn () => app(Settings::class)->flush());
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['group'] ?? null, fn (Builder $q, string $value) => $q->where('group', $value))
            ->when($filters['key'] ?? null, fn (Builder $q, string $value) => $q->whereLike('key', "%{$value}%"))
            ->when($filters['name'] ?? null, fn (Builder $q, string $value) => $q->whereLike('name', "%{$value}%"));
    }
}
