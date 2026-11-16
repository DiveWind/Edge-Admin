<?php

namespace App\Models;

use App\Collections\TreeCollection;
use App\Enums\PermissionType;
use App\Models\Concerns\StoresDatesInAppTimezone;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 权限点由 config/permissions.php 声明，经 `permission:sync` 同步，不通过接口增删。
 */
#[CollectedBy(TreeCollection::class)]
#[Fillable(['parent_id', 'name', 'code', 'type', 'sort'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory, StoresDatesInAppTimezone;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'type' => PermissionType::class,
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
