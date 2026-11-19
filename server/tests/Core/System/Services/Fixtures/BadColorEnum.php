<?php

namespace Tests\Core\System\Services\Fixtures;

use App\Core\System\Contracts\{HasColor, HasLabel};

/**
 * 颜色不合法的枚举：EnumRegistry 拒绝登记。
 */
enum BadColorEnum: string implements HasColor, HasLabel
{
    case One = 'one';

    public function label(): string
    {
        return 'One';
    }

    public function color(): string
    {
        return 'red; background: url(x)';
    }
}
