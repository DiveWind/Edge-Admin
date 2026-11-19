<?php

namespace Tests\Core\System\Services\Fixtures;

/**
 * 没有实现 HasLabel 的枚举：EnumRegistry 拒绝登记。
 */
enum PlainEnum: string
{
    case One = 'one';
}
