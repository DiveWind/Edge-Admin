<?php

namespace App\Core\System\Services;

use App\Core\System\Contracts\DataScopeProvider;
use App\Core\System\Models\{Role, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;

/**
 * 数据范围“仅本人数据”（`self`，设计文档 §8.4）：只看属于自己的记录。新建角色默认选用它。
 *
 * “属于自己”由模型声明（HasDataScope::dataScopeOwnerColumns()）：任一归属列的值等于用户 id 即可（多列为 OR）。
 * 默认只有创建人 `created_by`（由 Action 写入，§8.5）；User 另含 `id`，“仅本人”的操作者在用户列表中能看到自己。
 * 没有使用 HasDataScope 的模型按 `created_by` 判断。constrain() 与 contains() 读取同一组列，保持同一规则。
 */
class SelfDataScope implements DataScopeProvider
{
    /**
     * 默认的归属列：记录的创建人。
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
        $columns = $this->ownerColumnsOf($query->getModel());

        $query->where(function (Builder $query) use ($columns, $user): void {
            foreach ($columns as $column) {
                $query->orWhere($query->qualifyColumn($column), $user->getKey());
            }
        });
    }

    /**
     * @param  Collection<int, Role>  $roles
     */
    public function contains(Model $record, User $user, Collection $roles): bool
    {
        $key = $user->getKey();

        if (! is_scalar($key)) {
            return false;
        }

        return array_any($this->ownerColumnsOf($record), function (string $column) use ($record, $key): bool {
            $owner = $record->getAttribute($column);

            return is_scalar($owner) && (string) $owner === (string) $key;
        });
    }

    /**
     * 模型声明的归属列（HasDataScope::dataScopeOwnerColumns()）；没有声明时为 created_by。
     *
     * @return list<string>
     */
    private function ownerColumnsOf(Model $model): array
    {
        $columns = method_exists($model, 'dataScopeOwnerColumns') ? $model->dataScopeOwnerColumns() : null;

        if (! is_array($columns) || $columns === []) {
            return [self::OWNER_COLUMN];
        }

        return array_values(array_filter($columns, is_string(...)));
    }
}
