<?php

use App\Core\Generator\Editing\{MenusFile, UnexpectedFormatException};
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| MenusFile：把模型的菜单节点写入模块的 resources/menus.php（core:make-crud）
|--------------------------------------------------------------------------
*/

const MENUS_CATALOG = ['name' => 'blog', 'path' => '/blog', 'title' => 'blog.title', 'icon' => 'lucide:folder'];

/**
 * 菜单节点的声明。
 *
 * @return array{name: string, path: string, component: string, title: string, icon: string, buttons: array<string, string>}
 */
function menusFileMenu(string $model): array
{
    return ['name' => "blog.{$model}", 'path' => "/blog/{$model}", 'component' => "/blog/{$model}/list", 'title' => "blog.{$model}.title", 'icon' => 'lucide:list', 'buttons' => ['create' => 'common.create']];
}

/**
 * 执行生成的 menus.php，返回声明的节点。
 *
 * @return array<int, array<string, mixed>>
 */
function evaluateMenus(string $code): array
{
    $path = tempnam(sys_get_temp_dir(), 'menus');
    file_put_contents($path, $code);

    try {
        return require $path;
    } finally {
        File::delete($path);
    }
}

it('turns the empty declaration into a catalog with the menu', function (): void {
    $code = MenusFile::registerMenu("<?php\n\n/* 说明 */\n\nreturn [];\n", MENUS_CATALOG, menusFileMenu('post'));

    expect($code)->toStartWith("<?php\n\n/* 说明 */\n\nreturn [\n    [\n        'name' => 'blog',")
        ->and(evaluateMenus((string) $code))->toBe([[
            'name' => 'blog', 'type' => 'catalog', 'path' => '/blog',
            'meta' => ['title' => 'blog.title', 'icon' => 'lucide:folder', 'order' => 100],
            'children' => [[
                'name' => 'blog.post', 'type' => 'menu', 'path' => '/blog/post', 'component' => '/blog/post/list',
                'meta' => ['title' => 'blog.post.title', 'icon' => 'lucide:list'], 'buttons' => ['create' => 'common.create'],
            ]],
        ]]);
});

it('appends further menus to the children of the catalog and is idempotent', function (): void {
    $code = (string) MenusFile::registerMenu("<?php\n\nreturn [];\n", MENUS_CATALOG, menusFileMenu('post'));
    $code = (string) MenusFile::registerMenu($code, MENUS_CATALOG, menusFileMenu('tag'));

    expect(array_column(evaluateMenus($code)[0]['children'], 'name'))->toBe(['blog.post', 'blog.tag'])
        ->and(MenusFile::registerMenu($code, MENUS_CATALOG, menusFileMenu('tag')))->toBeNull();
});

it('appends a new catalog after the existing ones', function (): void {
    $existing = "<?php\n\nreturn [\n    [\n        'name' => 'other',\n        'type' => 'catalog',\n        'children' => [],\n    ],\n];\n";

    expect(array_column(evaluateMenus((string) MenusFile::registerMenu($existing, MENUS_CATALOG, menusFileMenu('post'))), 'name'))->toBe(['other', 'blog']);
});

it('refuses a catalog whose children it cannot find', function (): void {
    $code = "<?php\n\nreturn [\n    [\n        'name' => 'blog',\n        'type' => 'catalog',\n        'children' => [],\n    ],\n];\n";

    expect(fn (): ?string => MenusFile::registerMenu($code, MENUS_CATALOG, menusFileMenu('post')))
        ->toThrow(function (UnexpectedFormatException $e): void {
            expect($e->snippet)->toContain("'name' => 'blog.post',");
        });
});
