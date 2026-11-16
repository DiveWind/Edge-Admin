<?php

namespace App\Enums;

/**
 * 角色数据权限范围。
 */
enum DataScope: string
{
    case All = 'all';
    case Custom = 'custom';
    case DepartmentAndChildren = 'department_and_children';
    case Department = 'department';
    case Self = 'self';

    /**
     * 范围越大权重越高，用于在多角色时给出一个"代表性"范围。
     */
    public function weight(): int
    {
        return match ($this) {
            self::All => 5,
            self::Custom => 4,
            self::DepartmentAndChildren => 3,
            self::Department => 2,
            self::Self => 1,
        };
    }
}
