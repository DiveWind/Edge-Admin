<?php

namespace App\Core\System\Models;

use App\Core\System\Enums\Status;
use App\Core\System\Observers\DictionaryObserver;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Database\Factories\Core\System\Models\DictItemFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, ObservedBy};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 字典项（多态别名 `dict_item`，见 SystemServiceProvider），设计文档 §6.7：
 *
 * - value：值（字符串，同一类型内唯一），InDict 校验的就是它；label：显示名（前端 i18n key 或纯文本）；
 * - color：给前端 Tag 用的颜色（antd 预设色名或 #RGB / #RRGGBB），可空；sort：排序；status：停用的项不下发、不能通过 InDict；
 * - is_builtin：模块在 DictRegistry 中声明的字典项，由 core:sync-dicts 维护：不能删除，value 不能修改。
 *
 * 写入只在 System 模块的 Action 与同步命令中进行（设计文档 §5.2）；写入后（事务提交后）清除字典缓存（DictionaryObserver）。
 *
 * @property int $id
 */
#[Fillable(['value', 'label', 'color', 'sort', 'status'])]
#[ObservedBy(DictionaryObserver::class)]
class DictItem extends Model
{
    /** @use HasFactory<DictItemFactory> */
    use HasFactory, UsesAppTimezone;

    /**
     * value 的最大长度。
     */
    public const int VALUE_MAX_LENGTH = 64;

    /**
     * color 的格式：antd Tag 的预设色名（小写字母与 -，如 success、processing、geekblue）或 #RGB / #RRGGBB。
     */
    public const string COLOR_PATTERN = '/^([a-z][a-z-]*|#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6})$/';

    /**
     * 所属的字典类型。
     *
     * @return BelongsTo<DictType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DictType::class, 'dict_type_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'status' => Status::class,
            'is_builtin' => 'boolean',
        ];
    }
}
