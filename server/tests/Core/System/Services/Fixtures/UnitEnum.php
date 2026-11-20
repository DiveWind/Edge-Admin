<?php

namespace Tests\Core\System\Services\Fixtures;

use App\Support\Enums\HasLabel;

/**
 * 不是 backed enum（没有值）：EnumRegistry 拒绝登记。
 */
enum UnitEnum implements HasLabel
{
    case One;

    public function label(): string
    {
        return 'One';
    }
}
