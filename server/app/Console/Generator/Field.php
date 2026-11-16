<?php

namespace App\Console\Generator;

use Illuminate\Support\Str;

/**
 * 生成器字段：由 `name:type[:argument][:rules]` 解析而来，提供各模板片段需要的代码表达式。
 *
 * 返回 null 的表达式表示"该字段不参与此片段"（渲染时跳过），见 StubRenderer。
 */
class Field
{
    public const array TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'json', 'status', 'foreign', 'dict'];

    protected const array PRESENCE_RULES = ['required', 'nullable', 'sometimes', 'present', 'filled'];

    /**
     * @param  list<string>  $rules  用户在 DSL 中透传的校验规则
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $argument = null,
        public readonly array $rules = [],
    ) {}

    /**
     * foreign 字段可用的选项接口路由段（`/api/options/<optionsRoute>`），由生成命令按 routes/api.php 检测后设置；
     * 为 null 时前端下拉生成 TODO 占位。
     */
    public ?string $optionsRoute = null;

    public function isNullable(): bool
    {
        return in_array('nullable', $this->rules, true);
    }

    /**
     * 是否必填：声明了 required，或未声明任何存在性规则（默认 required）。
     */
    public function isRequired(): bool
    {
        $presence = array_intersect($this->rules, self::PRESENCE_RULES);

        return $presence === [] || in_array('required', $presence, true);
    }

    /**
     * 生效的 max 规则值（字符串未声明时为默认的 255），无则 null。
     */
    public function maxRule(): ?int
    {
        foreach ($this->rules as $rule) {
            if (preg_match('/^max:(\d+)$/', $rule, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return $this->type === 'string' ? 255 : null;
    }

    /**
     * foreign 关联表对应的选项接口路由段（article_categories → article-categories）。
     */
    public function relatedRoute(): ?string
    {
        return $this->type === 'foreign' && $this->argument !== null ? str_replace('_', '-', $this->argument) : null;
    }

    /**
     * 字符串长度：取规则中的 max:N（≤ 255），否则为 null（使用默认长度）。
     */
    public function maxLength(): ?int
    {
        foreach ($this->rules as $rule) {
            if (preg_match('/^max:(\d+)$/', $rule, $matches) === 1 && (int) $matches[1] <= 255) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * 外键关联的模型类名（foreign:article_categories → ArticleCategory）。
     */
    public function relatedModel(): ?string
    {
        return $this->type === 'foreign' && $this->argument !== null ? Str::studly(Str::singular($this->argument)) : null;
    }

    /**
     * 外键关联方法名（category_id → category）。
     */
    public function relationName(): ?string
    {
        return $this->type === 'foreign' ? Str::camel(Str::beforeLast($this->name, '_id')) : null;
    }

    /**
     * 片段中可用的 `{{ field.* }}` 变量。布尔值为 `'true'` / `''`（用于 `{{#if field.required}}`），
     * 不适用的值为 null（片段直接引用时跳过该字段）。
     *
     * @return array<string, string|null>
     */
    public function variables(): array
    {
        return [
            'name' => $this->name,
            'camel' => Str::camel($this->name),
            'label' => Str::headline($this->type === 'foreign' ? Str::beforeLast($this->name, '_id') : $this->name),
            'type' => $this->type,
            'argument' => $this->argument,
            'dict' => $this->type === 'dict' ? $this->argument : null,
            'related' => $this->relatedModel(),
            'relation' => $this->relationName(),
            'related_route' => $this->relatedRoute(),
            'options_route' => $this->optionsRoute,
            'required' => $this->isRequired() ? 'true' : '',
            'nullable' => $this->isNullable() ? 'true' : '',
            'max' => $this->maxRule() === null ? null : (string) $this->maxRule(),
            'migration' => $this->migration(),
            'cast' => $this->cast(),
            'rules' => $this->validationRules(),
            'filter_rule' => $this->filterRule(),
            'filter_scope' => $this->filterScope(),
            'factory' => $this->factory(),
            'resource' => $this->resource(),
            'test_value' => $this->testValue(),
            'ts_type' => $this->tsType(),
            'value_type' => $this->valueType(),
            'web_search' => $this->filterRule() === null ? ', search: false' : '',
        ];
    }

    /**
     * 各片段需要的 use 导入（类名全称）。
     *
     * @return list<string>
     */
    public function imports(string $section): array
    {
        $model = $this->relatedModel() === null ? [] : ['App\\Models\\'.$this->relatedModel()];

        return match ($section) {
            'cast' => $this->type === 'status' ? ['App\\Enums\\Status'] : [],
            'rules' => match ($this->type) {
                'status' => ['App\\Enums\\Status', 'Illuminate\\Validation\\Rule'],
                'foreign' => ['Illuminate\\Validation\\Rule'],
                'dict' => ['App\\Rules\\InDict'],
                default => [],
            },
            'filter_rule' => $this->type === 'status' ? ['App\\Enums\\Status', 'Illuminate\\Validation\\Rule'] : [],
            'factory' => $this->type === 'status' ? ['App\\Enums\\Status'] : $model,
            'relation' => $model === [] ? [] : [...$model, 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo'],
            'test_payload' => $model,
            'test_setup' => $this->type === 'dict' ? ['App\\Models\\DictItem', 'App\\Models\\DictType'] : [],
            'test_assertion' => $this->type === 'datetime' ? ['Carbon\\CarbonImmutable'] : [],
            default => [],
        };
    }

    public function migration(): string
    {
        $column = match ($this->type) {
            'string' => $this->maxLength() === null ? "\$table->string('{$this->name}')" : "\$table->string('{$this->name}', {$this->maxLength()})",
            'text' => "\$table->text('{$this->name}')",
            'integer' => "\$table->integer('{$this->name}')",
            'decimal' => "\$table->decimal('{$this->name}', 10, 2)",
            'boolean' => "\$table->boolean('{$this->name}')->default(false)",
            'date' => "\$table->date('{$this->name}')",
            'datetime' => "\$table->dateTime('{$this->name}')",
            'json' => "\$table->json('{$this->name}')",
            'status' => "\$table->string('{$this->name}', 20)->default('active')->index()",
            'dict' => "\$table->string('{$this->name}', 64)->index()",
            'foreign' => $this->isNullable()
                ? "\$table->foreignId('{$this->name}')->nullable()->constrained('{$this->argument}')->nullOnDelete()"
                : "\$table->foreignId('{$this->name}')->constrained('{$this->argument}')->restrictOnDelete()",
            default => "\$table->string('{$this->name}')",
        };

        return $this->isNullable() && $this->type !== 'foreign' ? $column.'->nullable()' : $column;
    }

    public function cast(): ?string
    {
        return match ($this->type) {
            'integer', 'foreign' => "'integer'",
            'decimal' => "'decimal:2'",
            'boolean' => "'boolean'",
            'date' => "'date'",
            'datetime' => "'datetime'",
            'json' => "'array'",
            'status' => 'Status::class',
            default => null,
        };
    }

    /**
     * 新增 / 编辑的校验规则：存在性规则（未声明时为 required）+ 类型规则 + 透传规则。
     */
    public function validationRules(): string
    {
        $presence = array_values(array_intersect($this->rules, self::PRESENCE_RULES));
        $passthrough = array_values(array_diff($this->rules, self::PRESENCE_RULES));
        $hasMax = collect($passthrough)->contains(fn (string $rule): bool => str_starts_with($rule, 'max:'));

        $typeRules = match ($this->type) {
            'string' => ["'string'", ...($hasMax ? [] : ["'max:255'"])],
            'text' => ["'string'"],
            'integer' => ["'integer'"],
            'decimal' => ["'numeric'"],
            'boolean' => ["'boolean'"],
            'date', 'datetime' => ["'date'"],
            'json' => ["'array'"],
            'status' => ['Rule::enum(Status::class)'],
            'foreign' => ["'integer'", "Rule::exists('{$this->argument}', 'id')"],
            'dict' => ["'string'", "new InDict('{$this->argument}')"],
            default => [],
        };

        $rules = collect([...($presence === [] ? ['required'] : $presence)])
            ->map(fn (string $rule): string => self::quote($rule))
            ->merge($typeRules)
            ->merge(collect($passthrough)->map(fn (string $rule): string => self::quote($rule)))
            ->unique()
            ->values();

        return '['.$rules->implode(', ').']';
    }

    public function filterRule(): ?string
    {
        return match ($this->type) {
            'string' => "['nullable', 'string', 'max:255']",
            'dict' => "['nullable', 'string', 'max:64']",
            'status' => "['nullable', Rule::enum(Status::class)]",
            'integer', 'foreign' => "['nullable', 'integer']",
            'boolean' => "['nullable', 'boolean']",
            'date' => "['nullable', 'date']",
            default => null,
        };
    }

    /**
     * 模型 filter 作用域中的一条语句：字符串模糊匹配，其余精确匹配。
     */
    public function filterScope(): ?string
    {
        $key = "\$filters['{$this->name}']";

        return match ($this->type) {
            'string' => "\$query->when({$key} ?? null, fn (Builder \$q, string \$value) => \$q->whereLike('{$this->name}', \"%{\$value}%\"));",
            'dict', 'status' => "\$query->when({$key} ?? null, fn (Builder \$q, string \$value) => \$q->where('{$this->name}', \$value));",
            'integer', 'foreign' => "\$query->when({$key} ?? null, fn (Builder \$q, int|string \$value) => \$q->where('{$this->name}', \$value));",
            'boolean' => "\$query->when(isset({$key}), fn (Builder \$q) => \$q->where('{$this->name}', filter_var({$key}, FILTER_VALIDATE_BOOLEAN)));",
            'date' => "\$query->when({$key} ?? null, fn (Builder \$q, string \$value) => \$q->whereDate('{$this->name}', \$this->asAppDateTime(\$value)));",
            default => null,
        };
    }

    public function factory(): string
    {
        $length = $this->maxLength() ?? 50;

        return match ($this->type) {
            'string' => $length >= 5 ? 'fake()->text('.min($length, 50).')' : "fake()->lexify('".str_repeat('?', $length)."')",
            'text' => 'fake()->paragraph()',
            'integer' => 'fake()->numberBetween(0, 100)',
            'decimal' => 'fake()->randomFloat(2, 0, 1000)',
            'boolean' => 'fake()->boolean()',
            'date' => 'fake()->date()',
            'datetime' => 'fake()->dateTime()',
            'json' => '[]',
            'status' => 'Status::Active',
            'foreign' => $this->relatedModel().'::factory()',
            'dict' => 'fake()->word()',
            default => 'null',
        };
    }

    public function resource(): string
    {
        $access = $this->isNullable() ? '?->' : '->';

        return match ($this->type) {
            'date' => "\$this->{$this->name}{$access}toDateString()",
            'datetime' => "\$this->{$this->name}{$access}toIso8601String()",
            default => "\$this->{$this->name}",
        };
    }

    /**
     * 生成的 Feature 测试中提交的合法值。
     */
    public function testValue(): string
    {
        $length = $this->maxLength() ?? 255;

        foreach ($this->rules as $rule) {
            if (str_starts_with($rule, 'in:') && $this->type !== 'dict') {
                return self::quote(explode(',', substr($rule, 3))[0]);
            }
        }

        return match ($this->type) {
            'string' => self::quote(mb_substr('Sample', 0, max(1, $length))),
            'text' => "'Sample text'",
            'integer' => '1',
            'decimal' => "'9.99'",
            'boolean' => 'true',
            'date' => "'2026-01-01'",
            'datetime' => "'2026-01-01T08:00:00+00:00'",
            'json' => "['key' => 'value']",
            'status' => "'active'",
            'foreign' => $this->relatedModel().'::factory()->create()->id',
            'dict' => "'sample'",
            default => 'null',
        };
    }

    public function tsType(): string
    {
        $type = match ($this->type) {
            'integer', 'foreign' => 'number',
            'boolean' => 'boolean',
            'json' => 'Record<string, unknown>',
            'status' => 'API.Status',
            default => 'string',
        };

        return $this->isNullable() ? "{$type} | null" : $type;
    }

    /**
     * ProTable / ProForm 的 valueType。
     */
    public function valueType(): string
    {
        return match ($this->type) {
            'text' => 'textarea',
            'integer', 'decimal', 'foreign' => 'digit',
            'boolean' => 'switch',
            'date' => 'date',
            'datetime' => 'dateTime',
            'json' => 'jsonCode',
            'status' => 'select',
            default => 'text',
        };
    }

    /**
     * PHP 单引号字符串字面量。
     */
    protected static function quote(string $value): string
    {
        return "'".addcslashes($value, "'\\")."'";
    }
}
