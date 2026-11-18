<?php

use App\Core\Generator\Editing\{PhpImports, UnexpectedFormatException};
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| 导入语句按 Pint（pint.json）的格式输出
|--------------------------------------------------------------------------
*/

it('groups imports from the same namespace and sorts them like Pint', function (): void {
    $code = <<<'PHP'
        <?php

        namespace App\Modules\Content;

        use Illuminate\Support\Str;
        use App\Modules\Content\Models\Comment;
        use Illuminate\Support\Facades\Route;
        use App\Modules\Content\Models\Article;
        use Illuminate\Support\ServiceProvider;
        use App\Modules\Content\Models\aDraft;
        use LogicException;

        class ContentServiceProvider extends ServiceProvider {}
        PHP;

    expect(PhpImports::normalize($code))->toContain(<<<'PHP'
        namespace App\Modules\Content;

        use App\Modules\Content\Models\{Article, Comment, aDraft};
        use Illuminate\Support\Facades\Route;
        use Illuminate\Support\{ServiceProvider, Str};
        use LogicException;

        class ContentServiceProvider
        PHP);
});

it('drops imports from the file namespace', function (): void {
    $code = "<?php\n\nnamespace App\\Modules\\Content;\n\nuse App\\Modules\\Content\\Helper;\nuse Illuminate\\Support\\Str;\n\nclass X {}\n";

    expect(PhpImports::normalize($code))->toBe("<?php\n\nnamespace App\\Modules\\Content;\n\nuse Illuminate\\Support\\Str;\n\nclass X {}\n");
});

it('adds imports to a file without any', function (string $code, string $expected): void {
    $imports = PhpImports::parse($code);

    expect($imports->add(Str::class))->toBe('Str')
        ->and($imports->render())->toBe($expected);
})->with([
    'namespaced' => ["<?php\n\nnamespace App\\Foo;\n\nclass X {}\n", "<?php\n\nnamespace App\\Foo;\n\nuse Illuminate\\Support\\Str;\n\nclass X {}\n"],
    'global' => ["<?php\n\nreturn [];\n", "<?php\n\nuse Illuminate\\Support\\Str;\n\nreturn [];\n"],
]);

it('returns the name already used for an imported class', function (): void {
    $imports = PhpImports::parse("<?php\n\nuse App\\Http\\Device\\DeviceServiceProvider as EntryProvider;\n\nreturn [];\n");

    expect($imports->add('App\Http\Device\DeviceServiceProvider'))->toBe('EntryProvider')
        ->and($imports->resolve('EntryProvider'))->toBe('App\Http\Device\DeviceServiceProvider')
        ->and($imports->resolve('Other'))->toBe('Other');
});

it('imports a class with the given alias when its name is taken', function (): void {
    $imports = PhpImports::parse("<?php\n\nuse App\\Http\\Device\\DeviceServiceProvider;\n\nreturn [];\n");

    expect($imports->add('App\Modules\Device\DeviceServiceProvider', 'ModulesDeviceServiceProvider'))->toBe('ModulesDeviceServiceProvider')
        ->and($imports->render())->toContain("use App\\Http\\Device\\DeviceServiceProvider;\nuse App\\Modules\\Device\\DeviceServiceProvider as ModulesDeviceServiceProvider;");

    expect(fn (): string => $imports->add('App\Core\Device\DeviceServiceProvider'))->toThrow(UnexpectedFormatException::class, '名称 DeviceServiceProvider 已被其他类使用');
});

it('does not import a class with the same name as the class it declares', function (): void {
    $imports = PhpImports::parse("<?php\n\nnamespace App\\Modules\\Content;\n\nclass Article {}\n");

    expect(fn (): string => $imports->add('App\Modules\Content\Models\Article'))->toThrow(UnexpectedFormatException::class);
});

it('leaves code it cannot rewrite safely unchanged', function (string $code): void {
    expect(PhpImports::normalize($code))->toBe($code)
        ->and(fn (): PhpImports => PhpImports::parse($code))->toThrow(UnexpectedFormatException::class);
})->with([
    'function import' => "<?php\n\nuse function Laravel\\Prompts\\select;\nuse Illuminate\\Support\\Str;\n",
    'comment between imports' => "<?php\n\nuse Illuminate\\Support\\Str;\n// note\nuse Illuminate\\Support\\Arr;\n",
]);
