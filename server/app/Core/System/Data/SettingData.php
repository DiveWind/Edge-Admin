<?php

namespace App\Core\System\Data;

use App\Core\System\Enums\SettingType;
use App\Core\System\Rules\InDict;
use LogicException;

/**
 * 模块声明的一个系统参数（SettingRegistry 中注册、已校验的形式，设计文档 §6.7）。
 *
 * 默认值二选一：`default`（声明中的值）或 `config`（作为默认值来源的 config 键，读取时取 config 的当前值）。
 * type、group、name、remark、public 是结构性字段，由同步命令 core:sync-settings 写入 settings 表。
 * 引用字典（dict，只用于 string、int）时，值必须是该字典类型的启用项（校验追加 InDict），前端按 dict 调用 /options/dicts 渲染下拉。
 */
final readonly class SettingData
{
    /**
     * @param  string  $key  完整的键 `<分组>.<参数名>`
     * @param  string  $group  分组标识
     * @param  string  $name  参数名（不带分组前缀，也是分组批量修改时的字段名）
     * @param  string  $title  显示名称（前端 i18n key 或纯文本），存入 settings.name
     * @param  mixed  $default  声明的默认值（$config 为 null 时使用，类型已校验）
     * @param  ?string  $config  作为默认值来源的 config 键
     * @param  list<mixed>  $rules  额外的校验规则（追加在类型规则之后）
     * @param  ?string  $dict  引用的字典类型（dict_types.code）
     */
    public function __construct(
        public string $key,
        public string $group,
        public string $name,
        public SettingType $type,
        public string $title,
        public ?string $remark = null,
        public mixed $default = null,
        public ?string $config = null,
        public bool $public = false,
        public array $rules = [],
        public ?string $dict = null,
    ) {}

    /**
     * 默认值：声明了 config 键时取 config 的当前值，否则为声明的 default。
     *
     * @throws LogicException config 的值不符合参数的类型
     */
    public function defaultValue(): mixed
    {
        if ($this->config === null) {
            return $this->type->cast($this->default);
        }

        $value = config($this->config);

        if (! $this->type->accepts($value)) {
            throw new LogicException("系统参数 {$this->key} 的默认值来源 config('{$this->config}') 不是 {$this->type->value} 类型。");
        }

        return $this->type->cast($value);
    }

    /**
     * 接口中的校验规则：类型规则 + 声明的额外规则，引用字典时再加 InDict（值可以为 null：恢复默认值）。
     *
     * @return list<mixed>
     */
    public function validationRules(): array
    {
        return [
            'nullable',
            ...$this->type->rules(),
            ...$this->rules,
            ...($this->dict === null ? [] : [new InDict($this->dict)]),
        ];
    }
}
