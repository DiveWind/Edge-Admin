<?php

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use RectorLaravel\Rector\ArrayDimFetch\{EnvVariableToEnvHelperRector, ServerVariableToRequestFacadeRector};
use RectorLaravel\Set\{LaravelLevelSetList, LaravelSetList};

/*
|--------------------------------------------------------------------------
| Rector
|--------------------------------------------------------------------------
|
| composer refactor 应用改动；CI 中以 `rector --dry-run` 检查（有改动即失败）。
| 规则集：PHP 8.5 语法升级、Laravel 13 升级（含属性改为 Attribute 写法）与 Laravel 代码质量规则。
|
| 跳过 SafeDeclareStrictTypesRector：项目沿用 Laravel 的写法，不使用 declare(strict_types=1)；
| 该规则只给它判断为“安全”的文件加声明，会造成同一项目中有的文件有、有的没有。
| tests/TestCase.php 有意直接读写 $_ENV / $_SERVER（重建应用前设置、之后还原环境变量），
| 不能改为 env() / Request::server()。
|
*/

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/bootstrap/providers.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/lang',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withCache(__DIR__.'/storage/framework/cache/rector')
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
    ])
    ->withSkip([
        SafeDeclareStrictTypesRector::class,
        EnvVariableToEnvHelperRector::class => [__DIR__.'/tests/TestCase.php'],
        ServerVariableToRequestFacadeRector::class => [__DIR__.'/tests/TestCase.php'],
    ]);
