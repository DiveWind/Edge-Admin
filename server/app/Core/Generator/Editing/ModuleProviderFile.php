<?php

namespace App\Core\Generator\Editing;

use App\Core\System\Services\{ExporterRegistry, MenuRegistry};
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * 在模块的 ServiceProvider 中登记多态别名与契约绑定（幂等），由 core:make-model / core:make-contract 调用。
 *
 * - 多态别名：追加到 boot() 中的 `Relation::morphMap([` … `]);`；还没有时插在 boot() 方法体的开头；
 * - 契约绑定：追加到 `public array $bindings = [` … `];`（框架注册 Provider 时自动绑定）；还没有时插在第一个方法之前；
 * - 语言包：`$this->loadTranslationsFrom(__DIR__.'/resources/lang', '<命名空间>');`，追加到 boot() 的末尾；
 * - 菜单与权限节点：boot() 注入 MenuRegistry，`$menus->register(require __DIR__.'/resources/menus.php');`；
 * - 导出类：boot() 注入 ExporterRegistry，`$exporters->register(require __DIR__.'/resources/exporters.php');`（条目在 resources/exporters.php，ClassMapFile）。
 *
 * 预期的格式与 core.module.provider.stub 生成的一致；找不到上述位置时抛出 UnexpectedFormatException，不猜测位置。
 */
class ModuleProviderFile
{
    /**
     * 登记多态别名 $alias => $model；已登记时返回 null，别名已被其他模型使用时抛出异常。
     *
     * @throws UnexpectedFormatException
     */
    public static function registerMorphAlias(string $code, string $alias, string $model): ?string
    {
        $snippet = "Relation::morphMap([\n    '{$alias}' => ".class_basename($model)."::class,\n]);";
        $imports = self::imports($code, $snippet);
        $name = self::import($imports, $model, $snippet);
        $entry = "'{$alias}' => {$name}::class,";

        if (preg_match('/^(?<indent>[ ]*)Relation::morphMap\(\[\n(?<body>(?:.*\n)*?)\k<indent>\]\);$/m', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            [$body, $offset] = $match['body'];

            if (preg_match('/^\s*\''.preg_quote($alias, '/').'\' => (?<class>[A-Za-z0-9_\\\\]+)::class,$/m', $body, $existing) === 1) {
                return $imports->resolve($existing['class']) === $model
                    ? null
                    : throw new UnexpectedFormatException("多态别名 {$alias} 已被 {$imports->resolve($existing['class'])} 使用", $snippet);
            }

            if (preg_match('/^\s*\'[^\']+\' => '.preg_quote($name, '/').'::class,$/m', $body) === 1) {
                return null;
            }

            $code = substr_replace($code, $body.$match['indent'][0].'    '.$entry."\n", $offset, strlen($body));
        } else {
            $code = self::prependToBoot($code, "Relation::morphMap([\n    {$entry}\n]);", $snippet);
        }

        return self::withImports($code, [Relation::class, $model], $snippet);
    }

    /**
     * 登记契约绑定 $contract => $implementation；已登记时返回 null，契约已绑定到其他实现时抛出异常。
     *
     * @throws UnexpectedFormatException
     */
    public static function registerBinding(string $code, string $contract, string $implementation): ?string
    {
        $snippet = "public array \$bindings = [\n    ".class_basename($contract).'::class => '.class_basename($implementation)."::class,\n];";
        $imports = self::imports($code, $snippet);
        $contractName = self::import($imports, $contract, $snippet);
        $entry = "{$contractName}::class => ".self::import($imports, $implementation, $snippet).'::class,';

        if (preg_match('/^(?<indent>[ ]*)public array \$bindings = \[\n(?<body>(?:.*\n)*?)\k<indent>\];$/m', $code, $match, PREG_OFFSET_CAPTURE) === 1) {
            [$body, $offset] = $match['body'];

            if (preg_match('/^\s*'.preg_quote($contractName, '/').'::class => (?<class>[A-Za-z0-9_\\\\]+)::class,$/m', $body, $existing) === 1) {
                return $imports->resolve($existing['class']) === $implementation
                    ? null
                    : throw new UnexpectedFormatException("契约 {$contract} 已绑定到 {$imports->resolve($existing['class'])}", $snippet);
            }

            $code = substr_replace($code, $body.$match['indent'][0].'    '.$entry."\n", $offset, strlen($body));
        } else {
            $property = implode("\n", [
                '    /**',
                '     * 本模块的契约及其实现（core:make-contract 登记）：框架注册本 Provider 时自动绑定到容器。',
                '     *',
                '     * @var array<class-string, class-string>',
                '     */',
                '    public array $bindings = [',
                '        '.$entry,
                '    ];',
            ]);

            if (preg_match('/^(?:    \/\*\*\n(?:    \s*\*.*\n)*?)?    (?:public |protected |private )?function /m', $code, $method, PREG_OFFSET_CAPTURE) !== 1) {
                throw new UnexpectedFormatException('找不到类中的第一个方法', $snippet);
            }

            $code = substr_replace($code, $property."\n\n", $method[0][1], 0);
        }

        return self::withImports($code, [$contract, $implementation], $snippet);
    }

    /**
     * 登记模块的语言包（命名空间 $namespace，目录 resources/lang）；已登记任何语言包时返回 null。
     *
     * @throws UnexpectedFormatException
     */
    public static function registerTranslations(string $code, string $namespace): ?string
    {
        $statement = "\$this->loadTranslationsFrom(__DIR__.'/resources/lang', '{$namespace}');";

        if (str_contains($code, 'loadTranslationsFrom(')) {
            return null;
        }

        return self::appendToBoot($code, $statement, $statement);
    }

    /**
     * 登记模块的菜单与权限节点（resources/menus.php）；已登记时返回 null。
     *
     * @throws UnexpectedFormatException
     */
    public static function registerMenus(string $code): ?string
    {
        $snippet = "public function boot(MenuRegistry \$menus): void\n{\n    \$menus->register(require __DIR__.'/resources/menus.php');\n}";

        if (str_contains($code, "/resources/menus.php'")) {
            return null;
        }

        [$code, $variable] = self::bootParameter($code, MenuRegistry::class, 'menus', $snippet);

        return self::withImports(self::appendToBoot($code, "\${$variable}->register(require __DIR__.'/resources/menus.php');", $snippet), [MenuRegistry::class], $snippet);
    }

    /**
     * 登记模块的导出类声明（resources/exporters.php，条目由 ClassMapFile 写入）；已登记时返回 null。
     *
     * @throws UnexpectedFormatException
     */
    public static function registerExporters(string $code): ?string
    {
        $snippet = "public function boot(ExporterRegistry \$exporters): void\n{\n    \$exporters->register(require __DIR__.'/resources/exporters.php');\n}";

        if (str_contains($code, "/resources/exporters.php'")) {
            return null;
        }

        [$code, $variable] = self::bootParameter($code, ExporterRegistry::class, 'exporters', $snippet);

        return self::withImports(self::appendToBoot($code, "\${$variable}->register(require __DIR__.'/resources/exporters.php');", $snippet), [ExporterRegistry::class], $snippet);
    }

    /**
     * boot() 中类型为 $class 的参数名；没有时追加参数 `$class $variable`。返回修改后的代码与参数名（不含 $）。
     *
     * @return array{string, string}
     *
     * @throws UnexpectedFormatException
     */
    private static function bootParameter(string $code, string $class, string $variable, string $snippet): array
    {
        if (preg_match('/public function boot\((?<parameters>[^)]*)\): void/', $code, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new UnexpectedFormatException('找不到 `public function boot(...): void` 方法', $snippet);
        }

        [$parameters, $offset] = $match['parameters'];
        $imports = self::imports($code, $snippet);

        foreach (array_filter(array_map(trim(...), explode(',', $parameters))) as $parameter) {
            if (preg_match('/^(?<type>[A-Za-z0-9_\\\\]+)\s+\$(?<name>\w+)$/', $parameter, $typed) === 1 && $imports->resolve($typed['type']) === $class) {
                return [$code, $typed['name']];
            }
        }

        if (preg_match('/\$'.$variable.'\b/', $parameters) === 1) {
            throw new UnexpectedFormatException("boot() 的参数 \${$variable} 已有其他类型", $snippet);
        }

        $name = self::import($imports, $class, $snippet);
        $added = trim($parameters) === '' ? "{$name} \${$variable}" : rtrim($parameters).", {$name} \${$variable}";

        return [self::withImports(substr_replace($code, $added, $offset, strlen($parameters)), [$class], $snippet), $variable];
    }

    /**
     * 在 boot() 方法体的末尾追加语句（与前面的语句空一行）；方法体只有 `//` 占位时替换占位。
     *
     * @throws UnexpectedFormatException
     */
    private static function appendToBoot(string $code, string $statement, string $snippet): string
    {
        if (preg_match('/^(?<indent>[ ]*)public function boot\([^)]*\): void\n\k<indent>\{\n(?<body>(?:.*\n)*?)\k<indent>\}$/m', $code, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new UnexpectedFormatException('找不到 `public function boot(...): void` 方法', $snippet);
        }

        $indent = $match['indent'][0].'    ';
        [$body, $offset] = $match['body'];
        $lines = implode("\n", array_map(fn (string $line): string => $indent.$line, explode("\n", $statement)))."\n";

        $updated = trim($body) === '//' || trim($body) === '' ? $lines : $body."\n".$lines;

        return substr_replace($code, $updated, $offset, strlen($body));
    }

    /**
     * 在 boot() 方法体的开头插入语句；方法体只有 `//` 占位时替换占位。
     *
     * @throws UnexpectedFormatException
     */
    private static function prependToBoot(string $code, string $statement, string $snippet): string
    {
        if (preg_match('/^(?<indent>[ ]*)public function boot\([^)]*\): void\n\k<indent>\{\n/m', $code, $match, PREG_OFFSET_CAPTURE) !== 1) {
            throw new UnexpectedFormatException('找不到 `public function boot(...): void` 方法', $snippet);
        }

        $indent = $match['indent'][0].'    ';
        $start = $match[0][1] + strlen($match[0][0]);
        $lines = implode("\n", array_map(fn (string $line): string => $indent.$line, explode("\n", $statement)));
        $placeholder = $indent."//\n".$match['indent'][0].'}';

        if (substr($code, $start, strlen($placeholder)) === $placeholder) {
            return substr_replace($code, $lines."\n", $start, strlen($indent."//\n"));
        }

        return substr_replace($code, $lines."\n\n", $start, 0);
    }

    /**
     * @throws UnexpectedFormatException
     */
    private static function imports(string $code, string $snippet): PhpImports
    {
        try {
            return PhpImports::parse($code);
        } catch (UnexpectedFormatException $e) {
            throw new UnexpectedFormatException($e->getMessage(), $snippet);
        }
    }

    /**
     * 代码中引用 $class 的名称（导入后）。短名称冲突时不猜测别名，抛出异常。
     *
     * @throws UnexpectedFormatException
     */
    private static function import(PhpImports $imports, string $class, string $snippet): string
    {
        try {
            return $imports->add($class);
        } catch (UnexpectedFormatException $e) {
            throw new UnexpectedFormatException($e->getMessage(), $snippet);
        }
    }

    /**
     * 修改后的代码补上导入。
     *
     * @param  list<string>  $classes
     *
     * @throws UnexpectedFormatException
     */
    private static function withImports(string $code, array $classes, string $snippet): string
    {
        $imports = self::imports($code, $snippet);

        foreach ($classes as $class) {
            self::import($imports, $class, $snippet);
        }

        return $imports->render();
    }
}
