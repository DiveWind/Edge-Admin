<?php

namespace App\Core\System\Data;

/**
 * 一个已声明参数的当前状态（Settings::entry() 的结果，管理接口的输出，设计文档 §6.7）。
 */
final readonly class SettingValueData
{
    /**
     * @param  mixed  $value  当前生效的值（库中改过的值，否则为默认值）
     * @param  mixed  $default  声明的默认值
     * @param  bool  $customized  库中是否有管理员改过的值
     */
    public function __construct(
        public SettingData $setting,
        public mixed $value,
        public mixed $default,
        public bool $customized,
    ) {}
}
