<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Override;

/**
 * core:make-data：模块中的只读数据对象（Data/<名称>Data），受限入口通过它读取模块的数据（设计文档 §5.2）。
 */
#[Signature('core:make-data
    {name : 数据对象名，如 DeviceConfig（生成 DeviceConfigData）}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中生成只读数据对象（Data）')]
class DataMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Data';

    protected function stubName(): string
    {
        return 'data';
    }

    protected function subNamespace(): string
    {
        return 'Data';
    }

    protected function suffix(): string
    {
        return 'Data';
    }

    protected function example(): string
    {
        return 'DeviceConfig';
    }
}
