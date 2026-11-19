<?php

namespace App\Core\System\Contracts;

use App\Core\System\Data\DictTypeData;
use InvalidArgumentException;

/**
 * 字典类型的声明（Core 的扩展点，设计文档 §5.5、§6.7）：单例，只保存启动时注册的静态声明。
 *
 * 各模块在自己 Provider 的 boot() 中注册本模块用到的字典类型与初始字典项，
 * 由同步命令 `php artisan core:sync-dicts` 统一写入 dict_types、dict_items：
 *
 * ```php
 * public function boot(DictRegistry $dicts): void
 * {
 *     $dicts->register(require __DIR__.'/resources/dicts.php');
 * }
 * ```
 *
 * 声明格式：类型的列表，每个类型 `code`、`name`、`remark`（可选）、`items`（`value`、`label`，可选 `color`、`sort`）。
 */
interface DictRegistry
{
    /**
     * 注册一组字典类型，通常是 `require` 模块的 resources/dicts.php 的结果。
     * 声明有误（code 格式或重复、value 重复、缺少 label 等）时抛出异常：这是开发期错误。
     *
     * @throws InvalidArgumentException
     */
    public function register(mixed $types): void;

    /**
     * 全部已声明的类型（按注册顺序），以 code 为键。
     *
     * @return array<string, DictTypeData>
     */
    public function all(): array;

    /**
     * 类型是否已声明。
     */
    public function has(string $code): bool;
}
