<?php

use App\Core\Message\Notifications\InboxNotification;
use App\Core\System\Contracts\{DataScopeProvider, Exporter};
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use App\Support\Enums\HasLabel;
use Illuminate\Container\Attributes\{Scoped, Singleton};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Notification;
use Illuminate\Support\{ServiceProvider, Str};

require_once __DIR__.'/helpers.php';

/*
|--------------------------------------------------------------------------
| Pest 预设
|--------------------------------------------------------------------------
|
| - php、security：完整使用；
| - laravel：它按默认目录（App\Models、App\Http\Requests、App\Providers……）检查，与设计文档 §4.2 的目录冲突，
|   因此排除 Core、业务模块与各入口的命名空间，只检查仍按默认目录组织的部分（App\Providers、App\Http\Middleware、App\Support）；
|   被排除部分的等价规则见下方“目录约定”，调试函数与 env() 的禁用见“常驻内存兼容”（扫描全部文件）。
|   领域层的业务异常放在 App\Support\Exceptions（Core、Modules 都要用，不属于 App\Exceptions 的应用级异常处理），也排除。
| - strict（要求 final 类与 declare(strict_types=1)）、relaxed（禁止 private 方法）与 Laravel 的写法冲突，不使用。
|
*/

arch('PHP 预设')->preset()->php();

arch('安全预设')->preset()->security();

arch('Laravel 预设（默认目录部分）')
    ->preset()
    ->laravel()
    ->ignoring([...archModules(), ...array_map(fn (string $entry): string => "App\\Http\\{$entry}", archEntries()), 'App\\Support\\Exceptions']);

/*
|--------------------------------------------------------------------------
| 目录约定（设计文档 §4.2–§4.4、§10.1）
|--------------------------------------------------------------------------
*/

test('入口目录、config/core.php 与 bootstrap/providers.php 一一对应', function (): void {
    $providers = require archBasePath('bootstrap/providers.php');

    expect(array_keys(archEntryConfigs()))->toEqualCanonicalizing(archEntries());

    foreach (archEntries() as $entry) {
        expect($providers)->toContain("App\\Http\\{$entry}\\{$entry}ServiceProvider")
            ->and(archBasePath("app/Http/{$entry}/routes.php"))->toBeFile();
    }
});

test('ServiceProvider 只放在 App\Providers，或入口、模块的根目录并以目录名命名', function (): void {
    foreach (archClasses() as $class) {
        if (! is_subclass_of($class, ServiceProvider::class)) {
            continue;
        }

        expect(preg_match('/^App\\\\(Providers\\\\\w+ServiceProvider|(Http|Core|Modules)\\\\(\w+)\\\\\3ServiceProvider)$/', $class))
            ->toBe(1, "{$class}：ServiceProvider 应为 App\\Providers\\*ServiceProvider 或 App\\{Http,Core,Modules}\\<Name>\\<Name>ServiceProvider");
    }
});

$models = archModelNamespaces();

arch('模型只放在 Core 与业务模块的 Models 目录')
    ->expect('App')
    ->classes()
    ->not->toExtend(Model::class)
    ->ignoring($models);

if ($models !== []) {
    arch('Models 目录中都是 Eloquent 模型')
        ->expect($models)
        ->classes()
        ->toExtend(Model::class);

    arch('模型写库前统一转换到应用时区（UsesAppTimezone）')
        ->expect($models)
        ->classes()
        ->toUseTrait(UsesAppTimezone::class);
}

test('模型用 Attribute 与 casts() 配置，不用属性写法', function (): void {
    $forbidden = ['fillable', 'guarded', 'hidden', 'visible', 'appends', 'touches', 'table', 'connection', 'primaryKey', 'keyType', 'dateFormat', 'casts', 'timestamps', 'incrementing', 'observables', 'with', 'withCount', 'perPage'];

    foreach (archClasses() as $class) {
        if (! is_subclass_of($class, Model::class)) {
            continue;
        }

        $own = array_map(
            fn (ReflectionProperty $property): string => $property->getName(),
            array_filter(new ReflectionClass($class)->getProperties(), fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $class),
        );

        expect(array_values(array_intersect($own, $forbidden)))->toBe([], "{$class}：请改用 #[Fillable]、#[Hidden]、#[Table] 等 Attribute 与 casts() 方法");
    }
});

if (($controllers = archNamespaces('Http/*/*/Controllers')) !== []) {
    arch('控制器以 Controller 结尾，只有资源方法与 __invoke')
        ->expect($controllers)
        ->classes()
        ->toHaveSuffix('Controller')
        ->not->toHavePublicMethodsBesides(['__construct', '__invoke', 'index', 'show', 'store', 'update', 'destroy', 'middleware']);
}

if (($requests = archNamespaces('Http/*/Requests', 'Http/*/*/Requests')) !== []) {
    arch('Form Request 继承 FormRequest，以 Request 结尾并声明 rules()')
        ->expect($requests)
        ->classes()
        ->toExtend(FormRequest::class)
        ->toHaveSuffix('Request')
        ->toHaveMethod('rules');
}

if (($resources = archNamespaces('Http/*/*/Resources')) !== []) {
    arch('API Resource 继承 JsonResource 并以 Resource 结尾')
        ->expect($resources)
        ->classes()
        ->toExtend(JsonResource::class)
        ->toHaveSuffix('Resource');
}

if (($middleware = archNamespaces('Http/Middleware', 'Http/*/Middleware')) !== []) {
    arch('中间件提供 handle()')
        ->expect($middleware)
        ->classes()
        ->toHaveMethod('handle');
}

if (($actions = archNamespaces('Core/*/Actions', 'Modules/*/Actions')) !== []) {
    arch('Action 一个类一个用例（handle()），不依赖 HTTP 层')
        ->expect($actions)
        ->classes()
        ->toHaveMethod('handle')
        ->not->toUse(['App\Http', Request::class, FormRequest::class]);
}

test('Action 只暴露 handle()：没有其他公开方法、公开静态方法与公开常量（设计文档 §5.4 的 Action 边界）', function (): void {
    $violations = [];

    foreach (archClasses() as $class) {
        if (preg_match('/^App\\\\(Core|Modules)\\\\[^\\\\]+\\\\Actions\\\\[^\\\\]+$/', $class) !== 1 || ! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        // 实现框架契约所需的方法（如 Fortify 的 UpdatesUserPasswords::update()）不算：它们是调用方约定的入口，内部转给 handle()
        // （Fortify 的契约只用 @method 声明方法）
        $contractMethods = [];

        foreach ($reflection->getInterfaces() as $interface) {
            $contractMethods = [...$contractMethods, ...array_map(fn (ReflectionMethod $method): string => $method->getName(), $interface->getMethods())];

            if (preg_match_all('/@method\s+(?:static\s+)?\S+\s+(\w+)\(/', (string) $interface->getDocComment(), $matches) > 0) {
                $contractMethods = [...$contractMethods, ...$matches[1]];
            }
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if ($method->getDeclaringClass()->getName() !== $class || in_array($name, ['handle', '__construct'], true)) {
                continue;
            }

            if ($method->isStatic()) {
                $violations[] = "{$class}::{$name}() 是公开静态方法";
            } elseif (! in_array($name, $contractMethods, true)) {
                $violations[] = "{$class}::{$name}() 是 handle() 以外的公开方法";
            }
        }

        foreach ($reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            if ($constant->getDeclaringClass()->getName() === $class) {
                $violations[] = "{$class}::{$constant->getName()} 是公开常量";
            }
        }
    }

    expect($violations)->toBe([], '共享的字段白名单、判断、查询与技术性重复不挂在 Action 上：按 §5.4 放到模型、枚举、Service、Rule、Query 或 Support；常量需要共享时放模型或枚举，否则改为 private');
});

if (($policies = archNamespaces('Core/*/Policies', 'Modules/*/Policies')) !== []) {
    arch('Policy 以 Policy 结尾')
        ->expect($policies)
        ->classes()
        ->toHaveSuffix('Policy');
}

if (($enums = archNamespaces('Core/*/Enums', 'Modules/*/Enums')) !== []) {
    arch('Enums 目录中都是枚举，并实现 HasLabel（显示名称由后端统一下发）')
        ->expect($enums)
        ->toBeEnums()
        ->toImplement(HasLabel::class);
}

if (($data = archNamespaces('Core/*/Data', 'Modules/*/Data')) !== []) {
    arch('Data 目录中都是只读类（readonly，修改用 clone with，设计文档 §4.3、§10.2）')
        ->expect($data)
        ->classes()
        ->toBeReadonly()
        ->toHaveSuffix('Data');
}

if (($exporters = archNamespaces('Core/*/Exporters', 'Modules/*/Exporters')) !== []) {
    arch('Exporters 目录中都是实现 Exporter 的导出类（设计文档 §6.10）')
        ->expect($exporters)
        ->classes()
        ->toImplement(Exporter::class)
        ->toHaveSuffix('Exporter');
}

test('注册表都是 #[Singleton] 的具体类，单例（含数据范围提供者）不注入 #[Scoped] 的请求级服务（设计文档 §5.5、§10.3）', function (): void {
    $violations = [];

    foreach (archClasses() as $class) {
        if (! class_exists($class) || ! str_starts_with($class, 'App\\')) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        // 数据范围提供者在启动时由 DataScopeRegistry 解析并保存在单例中，同样是常驻的实例
        $singleton = $reflection->getAttributes(Singleton::class) !== [] || $reflection->implementsInterface(DataScopeProvider::class);

        if (str_ends_with($class, 'Registry') && str_contains($class, '\\Services\\') && ! $singleton) {
            $violations[] = "{$class} 是注册表，应为 #[Singleton] 的具体类";
        }

        if (! $singleton) {
            continue;
        }

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            $dependency = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($dependency !== null && class_exists($dependency) && new ReflectionClass($dependency)->getAttributes(Scoped::class) !== []) {
                $violations[] = "单例 {$class} 注入了 #[Scoped] 的 {$dependency}（常驻内存下会跨请求保存数据）";
            }
        }
    }

    expect($violations)->toBe([]);
});

if (($contracts = archNamespaces('Core/*/Contracts', 'Modules/*/Contracts')) !== []) {
    arch('Contracts 目录中都是接口')
        ->expect($contracts)
        ->toBeInterfaces();
}

arch('富文本只经过统一的净化入口（App\Support\Html\HtmlSanitizers，模型用 SanitizedHtml，设计文档 §9）')
    ->expect('App')
    ->not->toUse('Symfony\Component\HtmlSanitizer')
    ->ignoring('App\Support\Html');

test('通知类都继承 InboxNotification，type() 格式合法且全局唯一（通知中心的统一结构，设计文档 §5.5、§6.9）', function (): void {
    $types = [];

    foreach (archClasses() as $class) {
        if (! is_subclass_of($class, Notification::class) || new ReflectionClass($class)->isAbstract()) {
            continue;
        }

        expect(is_subclass_of($class, InboxNotification::class))
            ->toBeTrue("{$class}：通知类须继承 ".InboxNotification::class.'（固定的输出结构与对外的类型名）');

        /** @var class-string<InboxNotification> $class */
        $type = $class::type();
        $expected = archIdentifier($class, Str::snake(Str::chopEnd(class_basename($class), 'Notification')));

        expect(str_ends_with($class, 'Notification'))->toBeTrue("{$class}：通知类以 Notification 结尾（不与同名的事件冲突）")
            ->and(preg_match(InboxNotification::TYPE_PATTERN, $type))->toBe(1, "{$class}::type() 的 [{$type}] 格式不合法")
            ->and($type)->toBe($expected, "{$class}::type() 应为类名去掉 Notification 的 snake_case（业务模块加模块前缀）：{$expected}")
            ->and($types)->not->toHaveKey($type, "{$class}::type() 的 [{$type}] 与 ".($types[$type] ?? '').' 重复');

        $types[$type] = $class;
    }

    expect($types)->not->toBeEmpty();
});

test('对外标识：Core 不带前缀，业务模块为 <模块>.<snake>（多态别名、枚举、导出、字典类型、数据范围、参数分组，设计文档 §4.4）', function (): void {
    $violations = [];
    $check = function (string $kind, string $name, string $module) use (&$violations): void {
        $prefix = Str::snake($module).'.';
        $valid = str_starts_with($module, 'Core\\')
            ? preg_match('/^[a-z][a-z0-9_]*$/', $name) === 1
            : str_starts_with($name, $prefix) && preg_match('/^[a-z][a-z0-9_]*$/', substr($name, strlen($prefix))) === 1;

        if (! $valid) {
            $violations[] = "{$kind} [{$name}]（{$module}）";
        }
    };

    foreach (Relation::morphMap() as $alias => $class) {
        if (preg_match('/^App\\\\(?<kind>Core|Modules)\\\\(?<module>[^\\\\]+)\\\\/', $class, $match) === 1) {
            $check('多态别名', $alias, ($match['kind'] === 'Core' ? 'Core\\' : '').$match['module']);
        }
    }

    foreach (glob(archBasePath('app/{Core,Modules}/*/resources'), GLOB_BRACE | GLOB_ONLYDIR) ?: [] as $directory) {
        $module = basename(dirname($directory));
        $module = (str_contains($directory, '/app/Core/') ? 'Core\\' : '').$module;
        $declarations = fn (string $file): array => is_file("{$directory}/{$file}") ? (array) require "{$directory}/{$file}" : [];

        foreach (array_keys($declarations('enums.php')) as $name) {
            $check('枚举', (string) $name, $module);
        }

        foreach (array_keys($declarations('exporters.php')) as $name) {
            $check('导出', (string) $name, $module);
        }

        foreach ($declarations('dicts.php') as $type) {
            $check('字典类型', is_array($type) && is_string($type['code'] ?? null) ? $type['code'] : '', $module);
        }

        foreach ($declarations('data-scopes.php') as $provider) {
            $check('数据范围', (is_string($provider) ? resolve($provider) : $provider)->key(), $module);
        }

        // 参数键为 `<分组>.<参数名>`，分组只有一段：业务模块的分组为模块名，或以 `<模块>_` 开头
        foreach (array_keys($declarations('settings.php')) as $group) {
            $group = (string) $group;
            $snake = Str::snake(basename(str_replace('\\', '/', $module)));

            if (! str_starts_with($module, 'Core\\') && $group !== $snake && ! str_starts_with($group, "{$snake}_")) {
                $violations[] = "参数分组 [{$group}]（{$module}）";
            }
        }
    }

    expect($violations)->toBe([], 'Core 的对外标识不带前缀；业务模块写成 <模块 snake>.<snake>（参数分组为模块名或 <模块>_<功能>）');
});

/*
|--------------------------------------------------------------------------
| 常驻内存兼容（设计文档 §10.3）
|--------------------------------------------------------------------------
|
| 默认运行在 PHP-FPM 下也必须遵守，保证随时可切换到 Octane + FrankenPHP。
|
*/

test('app/ 中没有可变的静态属性与静态变量（请求级状态用 #[Scoped]、Cache::memo() 或 Context）', function (): void {
    $violations = [];

    foreach (archClasses() as $class) {
        $reflection = new ReflectionClass($class);

        foreach ($reflection->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            $fromVendorTrait = array_any(
                $reflection->getTraits(),
                fn (ReflectionClass $trait): bool => ! str_starts_with($trait->getName(), 'App\\') && $trait->hasProperty($property->getName()),
            );

            if ($property->getDeclaringClass()->getName() === $class && ! $fromVendorTrait) {
                $violations[] = "{$class}::\${$property->getName()}";
            }
        }
    }

    foreach (archPhpFiles(['app']) as $file) {
        $tokens = array_values(array_filter(PhpToken::tokenize($file->getContents()), fn (PhpToken $token): bool => ! $token->isIgnorable()));

        foreach ($tokens as $index => $token) {
            if ($token->is(T_STATIC) && ($tokens[$index + 1] ?? null)?->is(T_VARIABLE)) {
                $violations[] = "{$file->getRelativePathname()}:{$token->line}";
            }
        }
    }

    expect($violations)->toBe([]);
});

test('不使用 exit、die 与调试函数', function (): void {
    $forbidden = ['exit', 'die', 'dd', 'ddd', 'dump', 'ray', 'var_dump', 'print_r', 'var_export', 'debug_zval_dump', 'xdebug_break'];

    $violations = [];

    foreach (archPhpFiles(['app', 'bootstrap/app.php', 'bootstrap/providers.php', 'config', 'database', 'lang', 'routes']) as $file) {
        foreach (archFunctionCalls($file->getContents(), $forbidden) as $call) {
            $violations[] = "{$file->getRelativePathname()}:{$call['line']} {$call['function']}()";
        }
    }

    expect($violations)->toBe([]);
});

test('config/core.php 的环境变量名与配置键对应：CORE_<功能>_<键>（设计文档 §4.4）', function (): void {
    $lines = file(archBasePath('config/core.php'), FILE_IGNORE_NEW_LINES) ?: [];
    $stack = [];
    $mismatches = [];
    $checked = 0;

    foreach ($lines as $number => $line) {
        if (preg_match('/^(?<indent> *)\'(?<key>[a-z0-9_]+)\' => (?<value>.*)$/', $line, $match) === 1) {
            $depth = intdiv(strlen($match['indent']), 4) - 1;
            $stack = array_slice($stack, 0, $depth);

            if (trim($match['value']) === '[') {
                $stack[] = $match['key'];

                continue;
            }

            if (preg_match("/env\\('(?<env>CORE_[A-Z0-9_]+)'/", $match['value'], $env) !== 1) {
                continue;
            }

            // 去掉模块段（system、monitor、message）与 apps 段：剩下的是“功能（或入口）+ 键”
            $path = [...$stack, $match['key']];
            $path = in_array($path[0] ?? null, ['system', 'monitor', 'message', 'apps'], true) ? array_slice($path, 1) : $path;
            $expected = 'CORE_'.strtoupper(implode('_', $path));
            $checked++;

            // 唯一的例外：富文本档位中的图片主机（项目可以增加档位，环境变量只作用于默认的 rich 档位）
            if ($expected !== $env['env'] && $env['env'] !== 'CORE_HTML_MEDIA_HOSTS') {
                $mismatches[] = 'config/core.php:'.($number + 1)." {$env['env']}，应为 {$expected}";
            }
        }
    }

    expect($mismatches)->toBe([])->and($checked)->toBeGreaterThan(10);
});

test('env() 只在 config/ 中使用（配置缓存后 env() 返回 null，代码中读取 config()）', function (): void {
    $violations = [];

    foreach (archPhpFiles(['app', 'bootstrap/app.php', 'bootstrap/providers.php', 'database', 'lang', 'routes', 'tests']) as $file) {
        foreach (archFunctionCalls($file->getContents(), ['env']) as $call) {
            $violations[] = "{$file->getRelativePathname()}:{$call['line']}";
        }
    }

    expect($violations)->toBe([]);
});

test('列表的筛选字段只有一份：列表请求校验的 filter.* 与 Query 的 query-builder 白名单一致（设计文档 §5.4）', function (): void {
    $mismatches = [];
    $pairs = [];

    // 列表 Query 与列表请求的对应关系取自控制器：同一个方法同时注入 `Queries\<名称>List` 与列表请求（Http 按 URL 资源命名、
    // 领域按模型命名，两边的名字不一定相同，如 ScheduleIndexRequest ↔ ScheduledTaskList，设计文档 §4.4）
    foreach (archClasses() as $controller) {
        if (preg_match('/^App\\\\Http\\\\[^\\\\]+\\\\[^\\\\]+\\\\Controllers\\\\/', $controller) !== 1) {
            continue;
        }

        foreach (new ReflectionClass($controller)->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $types = array_map(function (ReflectionParameter $parameter): string {
                $type = $parameter->getType();

                return $type instanceof ReflectionNamedType ? $type->getName() : '';
            }, $method->getParameters());

            $query = array_find($types, fn (string $type): bool => preg_match('/^App\\\\(?:Core|Modules)\\\\[^\\\\]+\\\\Queries\\\\[^\\\\]+List$/', $type) === 1);
            $request = array_find($types, fn (string $type): bool => is_subclass_of($type, FormRequest::class) && str_ends_with($type, 'IndexRequest'));

            if ($query !== null && $request !== null) {
                $pairs[$query][$request] = true;
            }
        }
    }

    foreach (archClasses() as $query) {
        if (preg_match('/^App\\\\(?:Core|Modules)\\\\[^\\\\]+\\\\Queries\\\\[^\\\\]+List$/', $query) !== 1) {
            continue;
        }

        if (! isset($pairs[$query])) {
            $mismatches[] = "{$query} 没有控制器同时注入它与列表请求（*IndexRequest）";

            continue;
        }

        // 按 builder() 的参数类型构造未保存的模型（只构造查询，不执行）
        $arguments = array_map(function (ReflectionParameter $parameter): mixed {
            $type = $parameter->getType();
            $class = $type instanceof ReflectionNamedType ? $type->getName() : null;

            return $class === 'array' ? [] : ($class !== null && class_exists($class) ? new $class : null);
        }, new ReflectionMethod($query, 'builder')->getParameters());

        $builder = resolve($query)->builder(...$arguments);
        $allowed = new ReflectionProperty($builder, 'allowedFilters')->getValue($builder);
        $allowed = collect($allowed)->map(fn (object $filter): string => method_exists($filter, 'getName') ? (string) $filter->getName() : '')->sort()->values()->all();

        foreach (array_keys($pairs[$query]) as $request) {
            $rules = array_keys(new $request()->rules());
            $validated = collect($rules)->filter(fn (string $key): bool => str_starts_with($key, 'filter.'))->map(fn (string $key): string => substr($key, 7))->sort()->values()->all();

            if ($validated !== $allowed) {
                $mismatches[] = "{$request} 校验的筛选 [".implode(', ', $validated)."] 与 {$query} 的白名单 [".implode(', ', $allowed).'] 不一致';
            }
        }
    }

    expect($mismatches)->toBe([], '每个可筛选的字段：Query 中登记（白名单），列表请求中校验取值；两边要同时加减')
        ->and($pairs)->not->toBeEmpty();
});
