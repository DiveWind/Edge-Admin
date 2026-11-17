<?php

use Symfony\Component\Finder\{Finder, SplFileInfo};

/*
|--------------------------------------------------------------------------
| Conventions
|--------------------------------------------------------------------------
|
| Pest 架构预设覆盖不到、但能机械检查的写法约定（说明见 docs/backend-guide.md「现代 Laravel 写法约定」）。
|
*/

/**
 * 项目目录下的 PHP 文件。
 *
 * @param  list<string>  $directories
 * @return list<SplFileInfo>
 */
function projectFiles(array $directories): array
{
    $root = dirname(__DIR__, 2);

    return iterator_to_array(Finder::create()->files()->name('*.php')->in(array_map(fn (string $directory): string => "{$root}/{$directory}", $directories))->notPath('cache'), false);
}

/**
 * 项目自有类 => 声明在该类自身上的属性名（不含继承自父类或 trait 的）。
 *
 * @return array<class-string, list<string>>
 */
function ownProperties(string $directory, string $namespace): array
{
    return collect(projectFiles([$directory]))
        ->map(fn (SplFileInfo $file): string => $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
        ->filter(fn (string $class): bool => class_exists($class))
        ->mapWithKeys(fn (string $class): array => [$class => collect((new ReflectionClass($class))->getProperties())
            ->filter(fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $class
                && ! collect(class_uses_recursive($class))->contains(fn (string $trait): bool => property_exists($trait, $property->getName())))
            ->map(fn (ReflectionProperty $property): string => $property->getName())
            ->values()
            ->all()])
        ->all();
}

test('models are configured with Eloquent attributes and casts() instead of properties', function () {
    $forbidden = ['fillable', 'guarded', 'hidden', 'visible', 'appends', 'touches', 'table', 'connection', 'dateFormat', 'casts', 'timestamps', 'incrementing', 'observables'];

    foreach (ownProperties('app/Models', 'App\\Models') as $model => $properties) {
        expect(array_intersect($properties, $forbidden))->toBe([], "{$model}：请改用 #[Fillable] / #[Hidden] / #[Table] / #[WithoutTimestamps] 等 Attribute 与 casts() 方法");
    }
});

test('commands declare their signature with console attributes', function () {
    foreach (ownProperties('app/Console/Commands', 'App\\Console\\Commands') as $command => $properties) {
        expect(array_intersect($properties, ['signature', 'description', 'name', 'aliases', 'hidden', 'help']))
            ->toBe([], "{$command}：请改用 #[Signature] / #[Description] 等 Attribute");
    }
});

test('jobs declare queue options with queue attributes', function () {
    foreach (ownProperties('app/Jobs', 'App\\Jobs') as $job => $properties) {
        expect(array_intersect($properties, ['tries', 'backoff', 'timeout', 'maxExceptions', 'failOnTimeout', 'deleteWhenMissingModels', 'uniqueFor', 'queue', 'connection', 'delay']))
            ->toBe([], "{$job}：请改用 #[Tries] / #[Backoff] / #[Timeout] / #[DeleteWhenMissingModels] 等 Attribute");
    }
});

test('jobs leave queue selection to the central Queue::route() table', function () {
    foreach (array_keys(ownProperties('app/Jobs', 'App\\Jobs')) as $job) {
        $attributes = collect((new ReflectionClass($job))->getAttributes())->map(fn (ReflectionAttribute $attribute): string => $attribute->getName())->all();

        expect(array_intersect($attributes, ['Illuminate\Queue\Attributes\Queue', 'Illuminate\Queue\Attributes\Connection']))
            ->toBe([], "{$job}：不要在 Job 上声明队列 / 连接，请在 AppServiceProvider::configureQueues() 的 Queue::route() 中登记（队列名来自 config('edge.queues')）");
    }
});

test('the routes file only describes structure: permissions are declared on controller actions', function () {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/api.php');

    expect($routes)
        ->not->toContain('->middlewareFor(')
        ->not->toContain("'can:")
        ->not->toContain('->can(');
});

test('imports from the same namespace are grouped into a single use statement', function () {
    foreach (projectFiles(['app', 'bootstrap', 'config', 'database', 'routes', 'tests']) as $file) {
        preg_match_all('/^use (?!function |const )([A-Za-z0-9_\\\\]+?)(?:\\\\\{[^}]*\}|\\\\[A-Za-z0-9_]+)(?: as \w+)?;$/m', $file->getContents(), $matches);

        $duplicated = collect($matches[1])->duplicates()->unique()->values()->all();

        expect($duplicated)->toBe([], "{$file->getPathname()}：同一命名空间的导入应合并为 use Namespace\\{A, B};（composer format 会自动处理）");
    }
});
