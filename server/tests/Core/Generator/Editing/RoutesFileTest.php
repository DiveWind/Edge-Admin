<?php

use App\Core\Generator\Editing\{RoutesFile, UnexpectedFormatException};

/*
|--------------------------------------------------------------------------
| RoutesFile：把模块的路由写入入口的 routes.php（core:make-crud）
|--------------------------------------------------------------------------
*/

const ROUTES_FILE = <<<'PHP'
    <?php

    use App\Http\Admin\System\Controllers\UserController;
    use Illuminate\Support\Facades\Route;

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::prefix('system')->name('system.')->group(function (): void {
            Route::apiResource('users', UserController::class);
        });
    });

    PHP;

it('adds the module group at the end of the authenticated group with its imports', function (): void {
    $code = RoutesFile::registerModuleRoutes(ROUTES_FILE, 'sample', 'Sample', ["Route::apiResource('posts', PostController::class);"], ['App\Http\Admin\Sample\Controllers\PostController']);

    expect($code)->toBe(<<<'PHP'
        <?php

        use App\Http\Admin\Sample\Controllers\PostController;
        use App\Http\Admin\System\Controllers\UserController;
        use Illuminate\Support\Facades\Route;

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::prefix('system')->name('system.')->group(function (): void {
                Route::apiResource('users', UserController::class);
            });

            // Sample
            Route::prefix('sample')->name('sample.')->group(function (): void {
                Route::apiResource('posts', PostController::class);
            });
        });

        PHP);
});

it('appends to an existing module group, skips the registered routes and groups the imports', function (): void {
    $code = (string) RoutesFile::registerModuleRoutes(ROUTES_FILE, 'sample', 'Sample', ["Route::apiResource('posts', PostController::class);"], ['App\Http\Admin\Sample\Controllers\PostController']);
    $code = (string) RoutesFile::registerModuleRoutes($code, 'sample', 'Sample', ["Route::apiResource('posts', PostController::class);", "Route::apiResource('tags', TagController::class);"], ['App\Http\Admin\Sample\Controllers\TagController']);

    expect($code)->toContain(
        'use App\Http\Admin\Sample\Controllers\{PostController, TagController};',
        "        Route::apiResource('posts', PostController::class);\n        Route::apiResource('tags', TagController::class);\n    });\n});",
    )
        ->and(substr_count($code, "Route::apiResource('posts'"))->toBe(1)
        ->and(RoutesFile::registerModuleRoutes($code, 'sample', 'Sample', ["Route::apiResource('tags', TagController::class);"], []))->toBeNull();
});

it('prints the group to paste when there is no authenticated group', function (): void {
    expect(fn (): ?string => RoutesFile::registerModuleRoutes("<?php\n\nRoute::get('/', fn () => []);\n", 'sample', 'Sample', ["Route::apiResource('posts', PostController::class);"], ['App\Http\Admin\Sample\Controllers\PostController']))
        ->toThrow(function (UnexpectedFormatException $e): void {
            expect($e->snippet)->toContain('use App\\Http\\Admin\\Sample\\Controllers\\PostController;', "Route::prefix('sample')->name('sample.')->group(function (): void {");
        });
});
