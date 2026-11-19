<?php

namespace App\Core\System\Models;

use App\Core\System\Enums\Status;
use App\Core\System\Observers\DictionaryObserver;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Database\Factories\Core\System\Models\DictTypeFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, ObservedBy};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 字典类型（多态别名 `dict_type`，见 SystemServiceProvider），设计文档 §6.7：
 *
 * - code：类型编码，唯一（小写点分，如 `content.article_source`），InDict 与选项接口按它查找；
 * - name：名称（前端 i18n key 或纯文本）；status：停用的类型不下发选项，InDict 一律不通过；remark：备注；
 * - is_builtin：模块在 DictRegistry 中声明的类型，由 core:sync-dicts 维护：不能删除，code 不能修改。
 *
 * 写入只在 System 模块的 Action 与同步命令中进行（设计文档 §5.2）；写入后（事务提交后）清除字典缓存（DictionaryObserver）。
 *
 * @property int $id
 */
#[Fillable(['code', 'name', 'status', 'remark'])]
#[ObservedBy(DictionaryObserver::class)]
class DictType extends Model
{
    /** @use HasFactory<DictTypeFactory> */
    use HasFactory, UsesAppTimezone;

    /**
     * 类型编码（code）的格式：小写点分，以字母开头，如 `user_gender`、`content.article_source`。
     */
    public const string CODE_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/';

    /**
     * 类型编码的最大长度。
     */
    public const int CODE_MAX_LENGTH = 64;

    /**
     * 类型编码是否合法（模块的声明与管理接口共用）。
     */
    public static function isValidCode(string $code): bool
    {
        return strlen($code) <= self::CODE_MAX_LENGTH && preg_match(self::CODE_PATTERN, $code) === 1;
    }

    /**
     * 字典项。
     *
     * @return HasMany<DictItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DictItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'is_builtin' => 'boolean',
        ];
    }
}
