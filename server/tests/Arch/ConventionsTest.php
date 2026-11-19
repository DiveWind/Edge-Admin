<?php

use App\Core\Message\Notifications\InboxNotification;
use App\Core\System\Contracts\HasLabel;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Notification;
use Illuminate\Support\ServiceProvider;

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
| - strict（要求 final 类与 declare(strict_types=1)）、relaxed（禁止 private 方法）与 Laravel 的写法冲突，不使用。
|
*/

arch('PHP 预设')->preset()->php();

arch('安全预设')->preset()->security();

arch('Laravel 预设（默认目录部分）')
    ->preset()
    ->laravel()
    ->ignoring([...archModules(), ...array_map(fn (string $entry): string => "App\\Http\\{$entry}", archEntries())]);

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

        expect(preg_match(InboxNotification::TYPE_PATTERN, $type))->toBe(1, "{$class}::type() 的 [{$type}] 应为小写点分、以模块名开头")
            ->and($types)->not->toHaveKey($type, "{$class}::type() 的 [{$type}] 与 ".($types[$type] ?? '').' 重复');

        $types[$type] = $class;
    }

    expect($types)->not->toBeEmpty();
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

test('env() 只在 config/ 中使用（配置缓存后 env() 返回 null，代码中读取 config()）', function (): void {
    $violations = [];

    foreach (archPhpFiles(['app', 'bootstrap/app.php', 'bootstrap/providers.php', 'database', 'lang', 'routes', 'tests']) as $file) {
        foreach (archFunctionCalls($file->getContents(), ['env']) as $call) {
            $violations[] = "{$file->getRelativePathname()}:{$call['line']}";
        }
    }

    expect($violations)->toBe([]);
});
