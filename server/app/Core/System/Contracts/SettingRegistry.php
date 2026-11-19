<?php

namespace App\Core\System\Contracts;

use App\Core\System\Data\{SettingData, SettingGroupData};
use InvalidArgumentException;

/**
 * 系统参数分组的声明（Core 的扩展点，设计文档 §5.5、§6.7）：单例，只保存启动时注册的静态声明。
 *
 * 各模块在自己 Provider 的 boot() 中注册本模块的参数分组（一个分组只属于一个模块），
 * 由同步命令 `php artisan core:sync-settings` 写入 settings 表，代码用 Settings 服务读取：
 *
 * ```php
 * public function boot(SettingRegistry $settings): void
 * {
 *     $settings->register(require __DIR__.'/resources/settings.php');
 * }
 * ```
 *
 * 声明格式：`分组 => ['title', 'order', 'settings' => [参数名 => ['type', 'name', 'remark', 'default' | 'config', 'public', 'rules', 'dict']]]`，
 * 参数的键为 `<分组>.<参数名>`。
 */
interface SettingRegistry
{
    /**
     * 注册一组参数分组，通常是 `require` 模块的 resources/settings.php 的结果。
     * 声明有误（分组重复、类型不合法、默认值与类型不符、default 与 config 不是恰好一个等）时抛出异常：这是开发期错误。
     *
     * @throws InvalidArgumentException
     */
    public function register(mixed $groups): void;

    /**
     * 全部分组，按 order、注册顺序排列。
     *
     * @return list<SettingGroupData>
     */
    public function groups(): array;

    /**
     * 分组；未声明时为 null。
     */
    public function group(string $key): ?SettingGroupData;

    /**
     * 全部已声明的参数（按分组顺序、声明顺序），以完整的键为键。
     *
     * @return array<string, SettingData>
     */
    public function all(): array;

    /**
     * 参数；未声明时为 null。
     */
    public function get(string $key): ?SettingData;

    /**
     * 参数是否已声明。
     */
    public function has(string $key): bool;
}
