<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\DataScopeResolver;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * 数据权限：约定字段 department_id（所属部门）与 created_by（创建人），
 * 可通过覆盖 dataScopeColumns() 修改。
 *
 * @mixin Model
 */
trait HasDataScope
{
    /**
     * 创建记录时自动填充创建人与所属部门（取当前登录用户）。
     * owner 列为主键（如 users 表）时不做填充。
     */
    public static function bootHasDataScope(): void
    {
        static::creating(function (Model $model): void {
            /** @var Model&self $model */
            ['department' => $department, 'owner' => $owner] = $model->dataScopeColumns();

            $user = Auth::user();

            if (! $user instanceof User || $owner === $model->getKeyName()) {
                return;
            }

            $model->setAttribute($owner, $model->getAttribute($owner) ?? $user->getKey());
            $model->setAttribute($department, $model->getAttribute($department) ?? $user->department_id);
        });
    }

    /**
     * @return array{department: string, owner: string}
     */
    public function dataScopeColumns(): array
    {
        return ['department' => 'department_id', 'owner' => 'created_by'];
    }

    /**
     * 仅保留 $user 可见的记录。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $visibility = app(DataScopeResolver::class)->resolve($user);

        if ($visibility->all) {
            return;
        }

        ['department' => $department, 'owner' => $owner] = $this->dataScopeColumns();

        $query->where(function (Builder $query) use ($visibility, $user, $department, $owner): void {
            $query->whereIn($query->qualifyColumn($department), $visibility->departmentIds);

            if ($visibility->self) {
                $query->orWhere($query->qualifyColumn($owner), $user->getKey());
            }
        });
    }

    /**
     * 单条记录是否对 $user 可见，规则与 visibleTo() 一致，用于 Policy。
     */
    public function isVisibleTo(User $user): bool
    {
        $visibility = app(DataScopeResolver::class)->resolve($user);

        if ($visibility->all) {
            return true;
        }

        ['department' => $department, 'owner' => $owner] = $this->dataScopeColumns();

        if (in_array($this->getAttribute($department), $visibility->departmentIds, true)) {
            return true;
        }

        return $visibility->self && $this->getAttribute($owner) === $user->getKey();
    }
}
