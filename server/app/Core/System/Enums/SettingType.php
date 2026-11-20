<?php

namespace App\Core\System\Enums;

use App\Support\Enums\HasLabel;

/**
 * 系统参数的类型（设计文档 §6.7）：决定接口的校验规则、保存前的转换与读取时的类型。
 *
 * | 类型 | 值 | 接口的校验 |
 * |---|---|---|
 * | string | 字符串（单行） | string，最长 255 |
 * | text | 字符串（多行） | string，最长 65535 |
 * | int | 整数 | integer |
 * | float | 数字（读取为 float） | numeric |
 * | bool | 布尔 | boolean（true / false / 0 / 1） |
 * | json | 数组或对象 | array |
 *
 * 库中的值（settings.value）以 JSON 编码保存，解码后用 accepts() 判断是否仍符合类型（声明改了类型时不符合，读取时回退到默认值）。
 *
 * 实现 HasLabel：显示名称由后端统一下发给前端（设计文档 §5.5、§10.1）。
 */
enum SettingType: string implements HasLabel
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'int';
    case Float = 'float';
    case Boolean = 'bool';
    case Json = 'json';

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::String => __('String'),
            self::Text => __('Text'),
            self::Integer => __('Integer'),
            self::Float => __('Decimal'),
            self::Boolean => __('Boolean'),
            self::Json => __('JSON'),
        };
    }

    /**
     * 接口中按类型的校验规则（声明的额外规则追加在后面）。
     *
     * @return list<string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::String => ['string', 'max:255'],
            self::Text => ['string', 'max:65535'],
            self::Integer => ['integer'],
            self::Float => ['numeric'],
            self::Boolean => ['boolean'],
            self::Json => ['array'],
        };
    }

    /**
     * 值是否符合类型（声明的默认值、库中解码后的值）。float 也接受整数。
     */
    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::String, self::Text => is_string($value),
            self::Integer => is_int($value),
            self::Float => is_int($value) || is_float($value),
            self::Boolean => is_bool($value),
            self::Json => is_array($value),
        };
    }

    /**
     * 已按 rules() 校验的输入转换为该类型的值：`"10"` → 10、`"1"` → true、`"1.5"` → 1.5；null（恢复默认值）保持 null。
     */
    public function fromInput(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::String, self::Text => is_scalar($value) ? (string) $value : $value,
            self::Integer => is_numeric($value) ? (int) $value : $value,
            self::Float => is_numeric($value) ? (float) $value : $value,
            self::Boolean => is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            self::Json => $value,
        };
    }

    /**
     * 符合类型的值转为读取时的类型（float 类型的整数转为 float），其余原样返回。
     */
    public function cast(mixed $value): mixed
    {
        return $this === self::Float && is_int($value) ? (float) $value : $value;
    }
}
