<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Override;

/**
 * core:make-action：模块中的 Action（写操作用例，一个类一个用例，handle() 方法，设计文档 §5.4）。
 */
#[Signature('core:make-action
    {name : Action 的类名，如 PublishArticle}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中生成 Action（写操作用例：一个类一个用例，handle() 方法）')]
class ActionMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Action';

    protected function stubName(): string
    {
        return 'action';
    }

    protected function subNamespace(): string
    {
        return 'Actions';
    }

    protected function example(): string
    {
        return 'PublishArticle';
    }
}
