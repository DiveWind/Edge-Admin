<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Console\GeneratorCommand;

/**
 * 列表请求（继承 App\Http\Requests\ListRequest）。与官方 make:request 行为一致：
 * 名称可带子命名空间（Content/TagIndexRequest），--force 覆盖，模板为 stubs/index-request.stub。
 */
#[Signature('make:index-request
    {name : 列表请求类名（如 Content/TagIndexRequest）}
    {--f|force : 文件已存在时覆盖}')]
#[Description('Create a new list (index) request class extending ListRequest')]
class IndexRequestMakeCommand extends GeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Request';

    protected function getStub(): string
    {
        return $this->laravel->basePath('stubs/index-request.stub');
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Http\Requests';
    }
}
