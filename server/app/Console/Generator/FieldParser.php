<?php

namespace App\Console\Generator;

use InvalidArgumentException;

/**
 * 解析字段 DSL：`name:type[:argument][:rules]`，多个字段以逗号分隔，例如
 * `name:string:required|max:50,category_id:foreign:article_categories,source:dict:article_source:nullable`。
 *
 * - foreign / dict 类型的下一段是参数（关联表名 / 字典编码），其后才是规则；
 * - rules 以 `|` 分隔、原样透传给 Laravel 校验（可含 `:`、`,`，如 `in:a,b`）；
 * - 逗号后紧跟 `字段名:` 才视为新字段，因此规则中的逗号不会被误拆。
 */
class FieldParser
{
    /**
     * 由生成器自动维护、不能在 DSL 中声明的字段。
     */
    protected const array RESERVED = ['id', 'created_at', 'updated_at', 'deleted_at', 'department_id', 'created_by'];

    /**
     * @return list<Field>
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $definition): array
    {
        $fields = [];

        foreach (self::split($definition) as $segment) {
            $field = self::parseField($segment);

            if (array_key_exists($field->name, $fields)) {
                throw new InvalidArgumentException("字段 [{$field->name}] 重复定义。");
            }

            $fields[$field->name] = $field;
        }

        return array_values($fields);
    }

    /**
     * @return list<string>
     */
    protected static function split(string $definition): array
    {
        $parts = preg_split('/,(?=\s*[a-z_][a-z0-9_]*:)/', trim($definition)) ?: [];

        return array_values(array_filter(array_map(trim(...), $parts), fn (string $part): bool => $part !== ''));
    }

    /**
     * @throws InvalidArgumentException
     */
    protected static function parseField(string $segment): Field
    {
        $parts = explode(':', $segment);
        $name = array_shift($parts);
        $type = array_shift($parts) ?? '';

        if (preg_match('/^[a-z_][a-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("字段名 [{$name}] 只能包含小写字母、数字和下划线。");
        }

        if (in_array($name, self::RESERVED, true)) {
            throw new InvalidArgumentException("字段 [{$name}] 由生成器自动维护，不能在 --fields 中声明。");
        }

        if (! in_array($type, Field::TYPES, true)) {
            throw new InvalidArgumentException("字段 [{$name}] 的类型 [{$type}] 不支持，可选：".implode(', ', Field::TYPES).'。');
        }

        $argument = null;

        if (in_array($type, ['foreign', 'dict'], true)) {
            $argument = array_shift($parts);

            if ($argument === null || preg_match('/^[a-z0-9_]+$/', $argument) !== 1) {
                throw new InvalidArgumentException("字段 [{$name}] 需要参数：{$type}:<".($type === 'foreign' ? '表名' : '字典编码').'>。');
            }
        }

        $rules = implode(':', $parts);

        return new Field(
            name: $name,
            type: $type,
            argument: $argument,
            rules: $rules === '' ? [] : array_values(array_filter(explode('|', $rules), fn (string $rule): bool => $rule !== '')),
        );
    }
}
