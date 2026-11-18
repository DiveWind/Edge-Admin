<?php

namespace App\Core\Generator\Editing;

use RuntimeException;

/**
 * 要修改的文件不是预期的格式：生成器不猜测位置，而是报错并打印需要手工粘贴的内容（$snippet）。
 */
class UnexpectedFormatException extends RuntimeException
{
    public function __construct(string $message, public readonly string $snippet = '')
    {
        parent::__construct($message);
    }
}
