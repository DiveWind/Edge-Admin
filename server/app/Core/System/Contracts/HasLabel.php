<?php

namespace App\Core\System\Contracts;

/**
 * 有显示名称的枚举（设计文档 §5.5、§10.1）：Core 与业务模块的枚举都实现它（tests/Arch 强制），
 * 在 EnumRegistry 中登记对外名称后，由选项接口 /options/enums 下发给前端（显式白名单，设计文档 §6.7）。core:make-enum 生成的枚举已实现。
 */
interface HasLabel
{
    /**
     * 显示名称（多语言：使用翻译函数，随应用语言变化）。
     */
    public function label(): string;
}
