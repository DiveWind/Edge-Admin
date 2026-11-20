<?php

use App\Core\Generator\Editing\{ModuleProviderFile, UnexpectedFormatException};

/*
|--------------------------------------------------------------------------
| ModuleProviderFile：在模块 Provider 的 boot() 中登记语言包、菜单与导出类（多态别名见 ModelMakeCommandTest）
|--------------------------------------------------------------------------
*/

/**
 * 只有 boot() 的模块 Provider。
 */
function moduleProvider(string $signature, string $body): string
{
    return "<?php\n\nnamespace App\\Modules\\Sample;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass SampleServiceProvider extends ServiceProvider\n{\n    public function boot({$signature}): void\n    {\n{$body}\n    }\n}\n";
}

it('registers an exporter, injecting the registry into boot()', function (): void {
    $code = (string) ModuleProviderFile::registerExporter(moduleProvider('', '        //'), 'sample.post', 'App\Modules\Sample\Exporters\PostExporter');

    expect($code)->toContain(
        "use App\\Core\\System\\Services\\ExporterRegistry;\nuse App\\Modules\\Sample\\Exporters\\PostExporter;\nuse Illuminate\\Support\\ServiceProvider;",
        "    public function boot(ExporterRegistry \$exporters): void\n    {\n        \$exporters->register([\n            'sample.post' => PostExporter::class,\n        ]);\n    }",
    );

    $code = (string) ModuleProviderFile::registerExporter($code, 'sample.tag', 'App\Modules\Sample\Exporters\TagExporter');

    expect($code)->toContain("            'sample.post' => PostExporter::class,\n            'sample.tag' => TagExporter::class,\n        ]);")
        ->and(ModuleProviderFile::registerExporter($code, 'sample.tag', 'App\Modules\Sample\Exporters\TagExporter'))->toBeNull()
        ->and(fn (): ?string => ModuleProviderFile::registerExporter($code, 'sample.tag', 'App\Modules\Sample\Exporters\OtherExporter'))->toThrow(UnexpectedFormatException::class, '已被');
});

it('reuses the registry parameter that boot() already has and adds a statement after the existing ones', function (): void {
    $code = moduleProvider('ExporterRegistry $registry', "        \$registry->register(['sample.post' => PostExporter::class]);");
    $code = str_replace('use Illuminate', "use App\\Core\\System\\Services\\ExporterRegistry;\nuse App\\Modules\\Sample\\Exporters\\PostExporter;\nuse Illuminate", $code);

    expect(ModuleProviderFile::registerExporter($code, 'sample.post', 'App\Modules\Sample\Exporters\PostExporter'))->toBeNull()
        ->and(ModuleProviderFile::registerExporter($code, 'sample.tag', 'App\Modules\Sample\Exporters\TagExporter'))->toContain(
            "    public function boot(ExporterRegistry \$registry): void\n    {\n        \$registry->register(['sample.post' => PostExporter::class]);\n\n        \$registry->register([\n            'sample.tag' => TagExporter::class,\n        ]);\n    }",
        );
});

it('registers the menus and the translations once', function (): void {
    $code = (string) ModuleProviderFile::registerMenus(moduleProvider('', '        //'));
    $code = (string) ModuleProviderFile::registerTranslations($code, 'sample');

    expect($code)->toContain(
        "    public function boot(MenuRegistry \$menus): void\n    {\n        \$menus->register(require __DIR__.'/resources/menus.php');\n\n        \$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'sample');\n    }",
        'use App\Core\System\Contracts\MenuRegistry;',
    )
        ->and(ModuleProviderFile::registerMenus($code))->toBeNull()
        ->and(ModuleProviderFile::registerTranslations($code, 'sample'))->toBeNull();
});

it('refuses a boot() parameter with the same name and another type', function (): void {
    expect(fn (): ?string => ModuleProviderFile::registerMenus(moduleProvider('string $menus', '        //')))->toThrow(UnexpectedFormatException::class, '已有其他类型');
});
