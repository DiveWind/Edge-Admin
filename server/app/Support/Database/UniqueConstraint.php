<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * 唯一索引冲突的兜底（设计文档 §5.4）：保存模型，撞上数据库的唯一索引时返回 422，错误挂在冲突的字段上。
 *
 * 表单中的 `Rule::unique` 只是为了尽早给出友好提示（可选）；并发的两个请求都通过表单校验后，后到的一个会撞上唯一索引——
 * 新增与修改的 Action 都用本类保存，保证这种情况同样是 422 而不是 500，命令行、队列等不经表单的调用方也受保护。
 *
 * 保存总是包在嵌套事务（保存点）中：PostgreSQL 在语句失败后会把整个事务置为中止状态，之后的任何语句（包括判断是哪个字段冲突的查询、
 * 调用方事务中的后续写入）都会失败，必须先回滚到保存点。MySQL、SQLite 没有这个问题，但写法统一，任何数据库下语义相同：
 * 冲突时只撤销这一次保存，调用方的事务仍然可用（是否整体回滚由调用方决定，通常是让 ValidationException 继续向上抛出）。
 */
final class UniqueConstraint
{
    /**
     * 保存 $model；撞上唯一索引时抛出 ValidationException。
     *
     * $fields 为可能冲突的字段 => 显示名（已翻译，用于 `validation.unique` 文案），按顺序：
     * - 只有一个字段时直接挂在它上面（复合唯一索引也只列它，如字典项的 value）；
     * - 多个字段时回滚到保存点后逐个查询（含软删除的记录、排除自身），第一个已被占用的字段即冲突字段；都没查到时取最后一个。
     *
     * @param  non-empty-array<string, string>  $fields
     *
     * @throws ValidationException
     */
    public static function save(Model $model, array $fields): void
    {
        try {
            $model->getConnection()->transaction(fn (): bool => $model->save());
        } catch (UniqueConstraintViolationException) {
            $field = self::conflictingField($model, $fields);

            throw ValidationException::withMessages([
                $field => [__('validation.unique', ['attribute' => $fields[$field]])],
            ]);
        }
    }

    /**
     * 冲突的字段。
     *
     * @param  non-empty-array<string, string>  $fields
     */
    private static function conflictingField(Model $model, array $fields): string
    {
        $columns = array_keys($fields);

        if (count($columns) === 1) {
            return $columns[0];
        }

        foreach ($columns as $column) {
            $taken = $model->newQueryWithoutScopes()
                ->where($column, $model->getAttribute($column))
                ->when($model->exists, fn (Builder $query) => $query->whereKeyNot($model->getKey()))
                ->exists();

            if ($taken) {
                return $column;
            }
        }

        return array_last($columns);
    }
}
