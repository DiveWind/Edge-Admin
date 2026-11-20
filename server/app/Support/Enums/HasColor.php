<?php

namespace App\Support\Enums;

/**
 * 有语义色的枚举（设计文档 §10.1）：可选，与 HasLabel 一起实现。
 *
 * 实现了它的枚举，选项接口 /options/enums 在每个选项中另外输出 `color`，前端的 Tag 据此着色。
 * 取值规则（PATTERN，字典项的 color 同一规则）：antd Tag 的预设色名（success、processing、error、warning、default、
 * blue 等）或 `#RGB` / `#RRGGBB`；EnumRegistry 登记时校验每个 case 的颜色。
 */
interface HasColor
{
    /**
     * 颜色值的格式：antd Tag 的预设色名或 `#RGB` / `#RRGGBB`。
     */
    public const string PATTERN = '/^([a-z][a-z-]*|#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6})$/';

    /**
     * 语义色。
     */
    public function color(): string;
}
