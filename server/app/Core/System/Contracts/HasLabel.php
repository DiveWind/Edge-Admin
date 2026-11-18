<?php

namespace App\Core\System\Contracts;

/**
 * 有显示名称的枚举（设计文档 §5.5、§10.1）：Core 与业务模块的枚举都实现它（tests/Arch 强制），
 * 由后端统一下发选项给前端（选项接口在后续步骤提供）。core:make-enum 生成的枚举已实现。
 */
interface HasLabel
{
    /**
     * 显示名称（多语言：使用翻译函数，随应用语言变化）。
     */
    public function label(): string;
}
