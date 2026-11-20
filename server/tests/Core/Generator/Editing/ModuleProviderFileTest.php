<?php

use App\Core\Generator\Editing\{ModuleProviderFile, UnexpectedFormatException};

/*
|--------------------------------------------------------------------------
| ModuleProviderFile：在模块 Provider 的 boot() 中登记语言包、菜单与导出类的声明文件（多态别名见 ModelMakeCommandTest）
|--------------------------------------------------------------------------
*/

/**
 * 只有 boot() 的模块 Provider。
 */
function moduleProvider(string $signature, string $body): string
{
    return "<?php\n\nnamespace App\\Modules\\Sample;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass SampleServiceProvider extends ServiceProvider\n{\n    public function boot({$signature}): void\n    {\n{$body}\n    }\n}\n";
}

it('registers the exporter declarations once, injecting the registry into boot()', function (): void {
    $code = (string) ModuleProviderFile::registerExporters(moduleProvider('', '        //'));

    expect($code)->toContain(
        "use App\\Core\\System\\Services\\ExporterRegistry;\nuse Illuminate\\Support\\ServiceProvider;",
        "    public function boot(ExporterRegistry \$exporters): void\n    {\n        \$exporters->register(require __DIR__.'/resources/exporters.php');\n    }",
    )
        ->and(ModuleProviderFile::registerExporters($code))->toBeNull();
});

it('reuses the registry parameter that boot() already has and adds a statement after the existing ones', function (): void {
    $code = moduleProvider('ExporterRegistry $registry', "        \$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'sample');");
    $code = str_replace('use Illuminate', "use App\\Core\\System\\Services\\ExporterRegistry;\nuse Illuminate", $code);

    expect(ModuleProviderFile::registerExporters($code))->toContain(
        "    public function boot(ExporterRegistry \$registry): void\n    {\n        \$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'sample');\n\n        \$registry->register(require __DIR__.'/resources/exporters.php');\n    }",
    );
});

it('registers the menus and the translations once', function (): void {
    $code = (string) ModuleProviderFile::registerMenus(moduleProvider('', '        //'));
    $code = (string) ModuleProviderFile::registerTranslations($code, 'sample');

    expect($code)->toContain(
        "    public function boot(MenuRegistry \$menus): void\n    {\n        \$menus->register(require __DIR__.'/resources/menus.php');\n\n        \$this->loadTranslationsFrom(__DIR__.'/resources/lang', 'sample');\n    }",
        'use App\Core\System\Services\MenuRegistry;',
    )
        ->and(ModuleProviderFile::registerMenus($code))->toBeNull()
        ->and(ModuleProviderFile::registerTranslations($code, 'sample'))->toBeNull();
});

it('refuses a boot() parameter with the same name and another type', function (): void {
    expect(fn (): ?string => ModuleProviderFile::registerMenus(moduleProvider('string $menus', '        //')))->toThrow(UnexpectedFormatException::class, '已有其他类型');
});
