<?php

namespace App\Enums;

/**
 * 通用启停状态。
 */
enum Status: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
