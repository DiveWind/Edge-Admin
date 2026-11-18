<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Override;

/**
 * core:make-query：模块中的 Query（复杂查询、统计指标，只读，设计文档 §5.4、§10.1）。
 */
#[Signature('core:make-query
    {name : Query 的类名，如 ArticleStatistics}
    '.self::MODULE_OPTIONS)]
#[Description('在模块中生成 Query（复杂查询、统计指标，只读）')]
class QueryMakeCommand extends ModuleGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Query';

    protected function stubName(): string
    {
        return 'query';
    }

    protected function subNamespace(): string
    {
        return 'Queries';
    }

    protected function example(): string
    {
        return 'ArticleStatistics';
    }
}
