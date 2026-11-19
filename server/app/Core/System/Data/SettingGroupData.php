<?php

namespace App\Core\System\Data;

/**
 * 模块声明的一个系统参数分组（SettingRegistry 中注册、已校验的形式，设计文档 §6.7）。
 */
final readonly class SettingGroupData
{
    /**
     * @param  string  $key  分组标识（参数键的第一段，如 `auth`）
     * @param  string  $title  标题（前端 i18n key 或纯文本）
     * @param  int  $order  分组的顺序
     * @param  array<string, SettingData>  $settings  本组的参数，以参数名（不带分组前缀）为键，按声明顺序
     */
    public function __construct(
        public string $key,
        public string $title,
        public int $order,
        public array $settings,
    ) {}
}
