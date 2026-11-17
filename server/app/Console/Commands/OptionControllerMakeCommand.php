<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

/**
 * 下拉选项控制器（App\Http\Controllers\Api\Options\<Name>OptionController，单动作，返回 OptionResource）。
 * 与官方 make:controller 行为一致：--force 覆盖、--model 指定模型，模板为 stubs/controller.option.stub。
 */
#[Signature('make:option-controller
    {name : 控制器类名（如 TagOptionController）}
    {--m|model= : 选项来源的模型（默认按类名推断，TagOptionController → Tag）}
    {--f|force : 文件已存在时覆盖}')]
#[Description('Create a new invokable options controller returning [{id, name}]')]
class OptionControllerMakeCommand extends GeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Controller';

    protected function getStub(): string
    {
        return $this->laravel->basePath('stubs/controller.option.stub');
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Http\Controllers\Api\Options';
    }

    /**
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $option = $this->option('model');
        $model = $this->qualifyModel(is_string($option) && $option !== '' ? $option : Str::before(class_basename($name), 'OptionController'));

        return str_replace(
            ['{{ namespacedModel }}', '{{ model }}'],
            [$model, class_basename($model)],
            parent::buildClass($name),
        );
    }
}
