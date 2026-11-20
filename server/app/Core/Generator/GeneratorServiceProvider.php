<?php

namespace App\Core\Generator;

use App\Core\Generator\Commands\{ActionMakeCommand, AppMakeCommand, ContractMakeCommand, ControllerMakeCommand, CoreGeneratorCommand, CrudMakeCommand, DataMakeCommand, EnumMakeCommand, ExporterMakeCommand, FactoryMakeCommand, MigrationMakeCommand, ModelMakeCommand, ModuleMakeCommand, PolicyMakeCommand, QueryMakeCommand, RequestMakeCommand, ResourceMakeCommand};
use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * 代码生成器（设计文档 §10.4）：注册 core:make-* 命令（只在命令行下），并把命令的模板加入官方的 `stub:publish`。
 *
 * 模板发布到项目的 `stubs/core.<名称>.stub` 后优先使用；也可只发布本模块的模板：`php artisan vendor:publish --tag=core-stubs`。
 */
class GeneratorServiceProvider extends ServiceProvider
{
    /**
     * 本模块的命令。
     *
     * @var list<class-string<CoreGeneratorCommand>>
     */
    public const array COMMANDS = [
        AppMakeCommand::class,
        ModuleMakeCommand::class,
        CrudMakeCommand::class,
        ModelMakeCommand::class,
        MigrationMakeCommand::class,
        FactoryMakeCommand::class,
        PolicyMakeCommand::class,
        ActionMakeCommand::class,
        QueryMakeCommand::class,
        ExporterMakeCommand::class,
        EnumMakeCommand::class,
        ContractMakeCommand::class,
        DataMakeCommand::class,
        ControllerMakeCommand::class,
        RequestMakeCommand::class,
        ResourceMakeCommand::class,
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands(self::COMMANDS);

        $stubs = self::stubs();

        $this->publishes(array_combine($stubs, array_map(fn (string $name): string => $this->app->basePath("stubs/core.{$name}.stub"), array_keys($stubs))), 'core-stubs');

        Event::listen(function (PublishingStubs $event) use ($stubs): void {
            foreach ($stubs as $name => $path) {
                $event->add($path, "core.{$name}.stub");
            }
        });
    }

    /**
     * 命令自带的模板：名称 => 路径。
     *
     * @return array<string, string>
     */
    public static function stubs(): array
    {
        $stubs = [];

        foreach (glob(CoreGeneratorCommand::defaultStub('*')) ?: [] as $path) {
            $stubs[basename($path, '.stub')] = $path;
        }

        return $stubs;
    }
}
