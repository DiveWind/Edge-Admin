<?php

namespace App\Enums;

/**
 * 系统参数值类型：决定 value 的校验规则与读写时的转换。
 */
enum SettingType: string
{
    case String = 'string';
    case Number = 'number';
    case Boolean = 'boolean';
    case Json = 'json';

    /**
     * 将数据库中的文本还原为对应类型的值。
     */
    public function decode(?string $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($this) {
            self::String => $raw,
            self::Number => str_contains($raw, '.') ? (float) $raw : (int) $raw,
            self::Boolean => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            self::Json => json_decode($raw, true),
        };
    }

    /**
     * 将值转换为数据库中存储的文本（json 类型接受数组或 JSON 字符串）。
     */
    public function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::String => is_scalar($value) ? (string) $value : (string) json_encode($value),
            self::Number => is_numeric($value) ? (string) ($value + 0) : '0',
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            self::Json => is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
