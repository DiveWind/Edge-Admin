<?php

namespace App\Console\Generator;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * 一个待生成模块的命名约定与模板变量（以 `Tag --module=content` 为例，注释中给出取值）。
 */
class ModuleBlueprint
{
    public readonly string $class;

    public readonly string $module;

    /**
     * @param  list<Field>  $fields
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $name,
        string $module,
        public readonly array $fields,
        public readonly bool $dataScope = false,
        public readonly bool $softDeletes = false,
        public readonly bool $withOptions = false,
    ) {
        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name) !== 1) {
            throw new InvalidArgumentException("模块名 [{$name}] 只能包含字母和数字，且以字母开头（如 Tag、ArticleTag）。");
        }

        if (preg_match('/^[a-z][a-z0-9\-]*$/', $module) !== 1) {
            throw new InvalidArgumentException("--module [{$module}] 只能包含小写字母、数字和短横线（如 content、crm）。");
        }

        if ($fields === []) {
            throw new InvalidArgumentException('请通过 --fields 至少声明一个字段。');
        }

        if ($withOptions && ! collect($fields)->contains(fn (Field $field): bool => $field->name === 'name')) {
            throw new InvalidArgumentException('--with-options 需要模块包含 name 字段（选项接口返回 [{id, name}]）。');
        }

        $this->class = Str::studly(Str::singular($name));
        $this->module = $module;
    }

    /**
     * 第一个 status 类型字段（选项接口只返回启用项），没有则为 null。
     */
    public function statusField(): ?string
    {
        return collect($this->fields)->first(fn (Field $field): bool => $field->type === 'status')?->name;
    }

    /** Tags */
    public function classPlural(): string
    {
        return Str::pluralStudly($this->class);
    }

    /** tags */
    public function table(): string
    {
        return Str::snake($this->classPlural());
    }

    /** tags（URL 段、路由名前缀） */
    public function route(): string
    {
        return Str::kebab($this->classPlural());
    }

    /** tag（apiResource 的路由参数名） */
    public function routeParameter(): string
    {
        return Str::snake($this->class);
    }

    /** Content */
    public function moduleStudly(): string
    {
        return Str::studly($this->module);
    }

    /** content.tag */
    public function permission(): string
    {
        return $this->module.'.'.Str::kebab($this->class);
    }

    /**
     * 模板变体（如 controller.data_scope.stub）。
     *
     * @return list<string>
     */
    public function variants(): array
    {
        return $this->dataScope ? ['data_scope'] : [];
    }

    /**
     * 模块级模板变量（全部模板与片段可用，完整清单见 docs/generator.md）。
     *
     * @return array<string, string|null>
     */
    public function variables(): array
    {
        $fillable = array_map(fn (Field $field): string => "'{$field->name}'", $this->fields);
        $sortable = ["'id'", ...array_map(
            fn (Field $field): string => "'{$field->name}'",
            array_values(array_filter($this->fields, fn (Field $field): bool => in_array($field->type, ['integer', 'decimal', 'date', 'datetime'], true))),
        ), "'created_at'"];
        $traits = ['HasFactory', 'StoresDatesInAppTimezone', ...($this->dataScope ? ['HasDataScope'] : []), ...($this->softDeletes ? ['SoftDeletes'] : [])];
        sort($traits);

        return [
            'class' => $this->class,
            'class_plural' => $this->classPlural(),
            'variable' => Str::camel($this->class),
            'variable_plural' => Str::camel($this->classPlural()),
            'title' => Str::headline($this->class),
            'title_plural' => Str::headline($this->classPlural()),
            'module' => $this->module,
            'module_studly' => $this->moduleStudly(),
            'module_title' => Str::headline($this->module),
            'table' => $this->table(),
            'route' => $this->route(),
            'route_parameter' => $this->routeParameter(),
            'permission' => $this->permission(),
            'controller_namespace' => 'App\\Http\\Controllers\\Api\\'.$this->moduleStudly(),
            'request_namespace' => 'App\\Http\\Requests\\'.$this->moduleStudly(),
            'fillable' => implode(', ', $fillable),
            'sortable' => implode(', ', $sortable),
            'traits' => implode(', ', $traits),
            'data_scope' => $this->dataScope ? 'true' : '',
            'soft_deletes' => $this->softDeletes ? 'true' : '',
            'with_options' => $this->withOptions ? 'true' : '',
            'status_field' => $this->statusField() ?? '',
            'sort_field' => collect($this->fields)->contains(fn (Field $field): bool => $field->name === 'sort' && $field->type === 'integer') ? 'sort' : '',
            'migration.extras' => $this->dataScope
                ? "            \$table->foreignId('department_id')->nullable()->index();\n            \$table->foreignId('created_by')->nullable()->index();"
                : '',
            'migration.soft_deletes' => $this->softDeletes ? '            $table->softDeletes();' : '',
            'casts.extras' => $this->dataScope ? "            'department_id' => 'integer',\n            'created_by' => 'integer',"
                : '',
            'factory.extras' => $this->dataScope ? "            'department_id' => null,\n            'created_by' => null," : '',
            'resource.extras' => $this->dataScope ? "            'department_id' => \$this->department_id,\n            'created_by' => \$this->created_by," : '',
            'web.path' => "/{$this->module}/{$this->route()}",
            'web.page_dir' => "{$this->module}/{$this->route()}",
            'web.component' => "{$this->class}List",
            'web.form_component' => "{$this->class}Form",
            'web.locale_prefix' => "pages.{$this->module}.{$this->route()}",
            'web.locale_file' => "{$this->module}-{$this->route()}",
            'web.locale_module' => Str::camel($this->module.'-'.$this->route()),
        ];
    }

    /**
     * 模型额外的 use 导入（trait）。
     *
     * @return list<string>
     */
    public function modelImports(): array
    {
        return [
            'App\\Models\\Concerns\\StoresDatesInAppTimezone',
            ...($this->dataScope ? ['App\\Models\\Concerns\\HasDataScope'] : []),
            ...($this->softDeletes ? ['Illuminate\\Database\\Eloquent\\SoftDeletes'] : []),
        ];
    }

    /**
     * 后端要创建的文件：模板名 => 相对 base_path 的路径（迁移的文件名由命令决定）。
     *
     * @return array<string, string>
     */
    public function backendFiles(): array
    {
        $module = $this->moduleStudly();

        return [
            'model' => "app/Models/{$this->class}.php",
            'factory' => "database/factories/{$this->class}Factory.php",
            'index_request' => "app/Http/Requests/{$module}/{$this->class}IndexRequest.php",
            'store_request' => "app/Http/Requests/{$module}/Store{$this->class}Request.php",
            'update_request' => "app/Http/Requests/{$module}/Update{$this->class}Request.php",
            'resource' => "app/Http/Resources/{$this->class}Resource.php",
            'policy' => "app/Policies/{$this->class}Policy.php",
            'controller' => "app/Http/Controllers/Api/{$module}/{$this->class}Controller.php",
            'test' => "tests/Feature/{$this->class}Test.php",
            ...($this->withOptions ? ['option_controller' => "app/Http/Controllers/Api/Options/{$this->class}OptionController.php"] : []),
        ];
    }

    /**
     * 前端要创建的文件：模板名 => 相对前端目录的路径。
     *
     * @return array<string, string>
     */
    public function webFiles(): array
    {
        $directory = "src/pages/{$this->module}/{$this->route()}";

        return [
            'web/index.tsx' => "{$directory}/index.tsx",
            'web/service.ts' => "{$directory}/service.ts",
            'web/data.d.ts' => "{$directory}/data.d.ts",
            'web/form.tsx' => "{$directory}/components/{$this->class}Form.tsx",
        ];
    }
}
