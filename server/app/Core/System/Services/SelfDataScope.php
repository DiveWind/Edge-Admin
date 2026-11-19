<?php

namespace App\Core\System\Services;

use App\Core\System\Contracts\DataScopeProvider;
use App\Core\System\Models\{Role, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;

/**
 * 数据范围“仅本人数据”（`self`，设计文档 §8.4）：只看自己创建的记录，按记录的 created_by 判断（由 Action 写入，§8.5）。
 * 新建角色默认选用它。使用 HasDataScope 的模型需要有 created_by 列。
 */
class SelfDataScope implements DataScopeProvider
{
    /**
     * 记录创建人的列。
     */
    public const string OWNER_COLUMN = 'created_by';

    public function key(): string
    {
        return DataScopeRegistry::SELF;
    }

    public function label(): string
    {
        return __('Own data only');
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Collection<int, Role>  $roles
     */
    public function constrain(Builder $query, User $user, Collection $roles): void
    {
        $query->where($query->qualifyColumn(self::OWNER_COLUMN), $user->getKey());
    }

    /**
     * @param  Collection<int, Role>  $roles
     */
    public function contains(Model $record, User $user, Collection $roles): bool
    {
        $owner = $record->getAttribute(self::OWNER_COLUMN);
        $key = $user->getKey();

        return is_scalar($owner) && is_scalar($key) && (string) $owner === (string) $key;
    }
}
