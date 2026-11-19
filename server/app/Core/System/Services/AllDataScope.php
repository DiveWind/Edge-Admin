<?php

namespace App\Core\System\Services;

use App\Core\System\Contracts\DataScopeProvider;
use App\Core\System\Models\{Role, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;

/**
 * 数据范围“全部数据”（`all`，设计文档 §8.4）：不限制。
 *
 * 任一启用角色选用它时，HasDataScope 直接不加条件，不再合并其他范围；这里的 constrain() 也不加条件。
 */
class AllDataScope implements DataScopeProvider
{
    public function key(): string
    {
        return DataScopeRegistry::ALL;
    }

    public function label(): string
    {
        return __('All data');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Collection<int, Role>  $roles
     */
    public function constrain(Builder $query, User $user, Collection $roles): void
    {
        //
    }

    /**
     * @param  Collection<int, Role>  $roles
     */
    public function contains(Model $record, User $user, Collection $roles): bool
    {
        return true;
    }
}
