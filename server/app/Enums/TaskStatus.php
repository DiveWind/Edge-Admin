<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
}
