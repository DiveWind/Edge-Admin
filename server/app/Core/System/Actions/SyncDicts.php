<?php

namespace App\Core\System\Actions;

use App\Core\System\Contracts\DictRegistry;
use App\Core\System\Data\{DictItemData, DictTypeData};
use App\Core\System\Enums\Status;
use App\Core\System\Models\{DictItem, DictType};
use App\Core\System\Services\Dictionary;
use Illuminate\Support\Facades\DB;

/**
 * 把 DictRegistry 中声明的字典类型与初始字典项同步到 dict_types、dict_items（设计文档 §6.7）：由 `core:sync-dicts` 调用，
 * DatabaseSeeder 与部署流程（§11.4）都执行它。幂等：再次执行没有变更。
 *
 * 1. 声明中有、库中没有的类型：新增并标记 is_builtin（启用），连同声明的字典项；库中已有同 code 的自定义类型时收编（标记 is_builtin）；
 * 2. 已有类型下：声明中有、库中没有的字典项新增为内置项；同 value 的自定义项收编。结构性字段只有标识（code、value 与所属类型），
 *    已有的类型与字典项的名称、备注、label、color、sort、status 以库为准，管理员的调整不被覆盖；
 * 3. 从声明中移除的内置字典项、内置类型（连同其全部内置项）：取消 is_builtin，转为自定义；不删除任何数据。
 *
 * 全部写入在一个事务中；最后清除字典缓存（种子数据关闭模型事件时 DictionaryObserver 不会执行）。
 * 字典项在结果中写作 `<类型>:<value>`。
 */
class SyncDicts
{
    public function __construct(
        private readonly DictRegistry $dicts,
        private readonly Dictionary $dictionary,
    ) {}

    /**
     * Execute the action.
     *
     * @return array{created: list<string>, adopted: list<string>, released: list<string>, created_items: list<string>, adopted_items: list<string>, released_items: list<string>}
     */
    public function handle(): array
    {
        $result = DB::transaction(function (): array {
            $result = ['created' => [], 'adopted' => [], 'released' => [], 'created_items' => [], 'adopted_items' => [], 'released_items' => []];

            /** @var array<string, DictType> $existing */
            $existing = DictType::query()->with('items')->get()->keyBy('code')->all();

            foreach ($this->dicts->all() as $code => $declaration) {
                $type = $existing[$code] ?? null;

                if ($type === null) {
                    $type = $this->createType($declaration);
                    $result['created'][] = $code;
                } elseif (! $type->is_builtin) {
                    $type->forceFill(['is_builtin' => true])->save();
                    $result['adopted'][] = $code;
                }

                $this->syncItems($type, $declaration, $result);
            }

            foreach ($existing as $code => $type) {
                if ($type->is_builtin && ! $this->dicts->has($code)) {
                    $type->forceFill(['is_builtin' => false])->save();
                    $result['released'][] = $code;

                    foreach ($type->items as $item) {
                        $this->release($code, $item, $result);
                    }
                }
            }

            return $result;
        });

        $this->dictionary->flush();

        return $result;
    }

    /**
     * 同步一个声明类型下的字典项：新增缺少的、收编同 value 的自定义项、释放不再声明的内置项。
     *
     * @param  array{created: list<string>, adopted: list<string>, released: list<string>, created_items: list<string>, adopted_items: list<string>, released_items: list<string>}  $result
     */
    private function syncItems(DictType $type, DictTypeData $declaration, array &$result): void
    {
        /** @var array<string, DictItem> $items */
        $items = $type->relationLoaded('items') ? $type->items->keyBy('value')->all() : [];

        foreach ($declaration->items as $value => $item) {
            $existing = $items[$value] ?? null;

            if ($existing === null) {
                $this->createItem($type, $item);
                $result['created_items'][] = "{$type->code}:{$value}";
            } elseif (! $existing->is_builtin) {
                $existing->forceFill(['is_builtin' => true])->save();
                $result['adopted_items'][] = "{$type->code}:{$value}";
            }
        }

        foreach ($items as $value => $item) {
            if (! $declaration->hasItem($value)) {
                $this->release($type->code, $item, $result);
            }
        }
    }

    /**
     * 不再声明的内置字典项转为自定义（自定义项不变）。
     *
     * @param  array{created: list<string>, adopted: list<string>, released: list<string>, created_items: list<string>, adopted_items: list<string>, released_items: list<string>}  $result
     */
    private function release(string $code, DictItem $item, array &$result): void
    {
        if (! $item->is_builtin) {
            return;
        }

        $item->forceFill(['is_builtin' => false])->save();
        $result['released_items'][] = "{$code}:{$item->value}";
    }

    /**
     * 新增内置类型：声明的名称、备注，启用。
     */
    private function createType(DictTypeData $declaration): DictType
    {
        $type = new DictType;

        $type->forceFill([
            'code' => $declaration->code,
            'name' => $declaration->name,
            'remark' => $declaration->remark,
            'status' => Status::Enabled,
            'is_builtin' => true,
        ])->save();

        return $type;
    }

    /**
     * 新增内置字典项：声明的 label、color、sort，启用。
     */
    private function createItem(DictType $type, DictItemData $declaration): void
    {
        new DictItem()->forceFill([
            'dict_type_id' => $type->id,
            'value' => $declaration->value,
            'label' => $declaration->label,
            'color' => $declaration->color,
            'sort' => $declaration->sort,
            'status' => Status::Enabled,
            'is_builtin' => true,
        ])->save();
    }
}
