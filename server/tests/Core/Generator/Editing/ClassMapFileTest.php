<?php

use App\Core\Generator\Editing\{ClassMapFile, UnexpectedFormatException};

/*
|--------------------------------------------------------------------------
| ClassMapFile：在模块的 resources/exporters.php 这类“名称 => 类”声明文件中登记一项
|--------------------------------------------------------------------------
*/

it('fills the empty declaration, then appends, importing the classes', function (): void {
    $code = "<?php\n\n/*\n| 说明\n*/\n\nreturn [];\n";

    $code = (string) ClassMapFile::register($code, 'sample.post', 'App\\Modules\\Sample\\Exporters\\PostExporter');

    expect($code)->toContain('use App\\Modules\\Sample\\Exporters\\PostExporter;', "return [\n    'sample.post' => PostExporter::class,\n];");

    $code = (string) ClassMapFile::register($code, 'sample.tag', 'App\\Modules\\Sample\\Exporters\\TagExporter');

    expect($code)->toContain('use App\\Modules\\Sample\\Exporters\\{PostExporter, TagExporter};', "    'sample.post' => PostExporter::class,\n    'sample.tag' => TagExporter::class,\n];");
});

it('is idempotent and refuses a name taken by another class', function (): void {
    $code = (string) ClassMapFile::register("<?php\n\nreturn [];\n", 'sample.post', 'App\\Modules\\Sample\\Exporters\\PostExporter');

    expect(ClassMapFile::register($code, 'sample.post', 'App\\Modules\\Sample\\Exporters\\PostExporter'))->toBeNull()
        ->and(fn (): ?string => ClassMapFile::register($code, 'sample.post', 'App\\Modules\\Sample\\Exporters\\OtherExporter'))->toThrow(UnexpectedFormatException::class, '已登记为')
        ->and(fn (): ?string => ClassMapFile::register("<?php\n\nreturn \$exporters;\n", 'sample.post', 'App\\Modules\\Sample\\Exporters\\PostExporter'))->toThrow(UnexpectedFormatException::class, 'return');
});
