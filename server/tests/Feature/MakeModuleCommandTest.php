<?php

use App\Console\Generator\FieldParser;
use App\Console\Generator\StubRenderer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
| 生成器在临时目录中运行：后端写入 edge.generator.base_path，前端写入 edge.generator.web_path，
| 两者都从仓库中的真实文件（含锚点）复制而来；模板使用仓库内的 stubs/edge。
*/

const ALL_FIELDS = 'title:string:required|max:100,body:text:nullable,price:decimal,quantity:integer,enabled:boolean,'
    .'starts_on:date:nullable,published_at:datetime:nullable,meta:json:nullable,status:status,'
    .'category_id:foreign:article_categories,source:dict:article_source,level:string:in:low,high';

beforeEach(function () {
    $this->root = storage_path('framework/testing/generator-'.Str::random(8));
    $this->base = "{$this->root}/server";
    $this->web = "{$this->root}/web";

    foreach (['config/permissions.php', 'routes/api.php', 'lang/zh_CN/operations.php', 'lang/en/operations.php'] as $file) {
        File::ensureDirectoryExists(dirname("{$this->base}/{$file}"));
        File::copy(base_path($file), "{$this->base}/{$file}");
    }

    File::ensureDirectoryExists("{$this->web}/config");
    File::ensureDirectoryExists("{$this->web}/src");
    File::put("{$this->web}/config/routes.ts", "const routes = [\n  // edge:routes\n];\n\nexport default routes;\n");
    File::put("{$this->web}/src/access.ts", "export type PermissionCode =\n  | 'dashboard.view'\n  // edge:permission-codes\n  ;\n");

    foreach (['zh-CN', 'en-US'] as $locale) {
        File::ensureDirectoryExists("{$this->web}/src/locales/{$locale}/pages");
        File::put("{$this->web}/src/locales/{$locale}.ts", "import menu from './{$locale}/menu';\n// edge:locale-imports\n\nexport default {\n  ...menu,\n  // edge:locale-spread\n};\n");
    }

    config(['edge.generator.base_path' => $this->base, 'edge.generator.web_path' => $this->web]);
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

/**
 * 生成器写入的全部文件（相对临时根目录）=> 内容。
 *
 * @return array<string, string>
 */
function generatedFiles(string $root): array
{
    return collect(File::allFiles($root))
        ->mapWithKeys(fn (SplFileInfo $file): array => [Str::after($file->getPathname(), $root.'/') => File::get($file->getPathname())])
        ->all();
}

test('it generates a complete backend and frontend module', function () {
    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'crm', '--fields' => ALL_FIELDS, '--data-scope' => true, '--soft-deletes' => true])
        ->assertSuccessful();

    $files = generatedFiles($this->root);

    expect(array_keys($files))->toContain(
        'server/app/Models/Ticket.php',
        'server/database/factories/TicketFactory.php',
        'server/app/Http/Requests/Crm/TicketIndexRequest.php',
        'server/app/Http/Requests/Crm/StoreTicketRequest.php',
        'server/app/Http/Requests/Crm/UpdateTicketRequest.php',
        'server/app/Http/Resources/TicketResource.php',
        'server/app/Policies/TicketPolicy.php',
        'server/app/Http/Controllers/Api/Crm/TicketController.php',
        'server/tests/Feature/TicketTest.php',
        'web/src/pages/crm/tickets/index.tsx',
        'web/src/pages/crm/tickets/service.ts',
        'web/src/pages/crm/tickets/data.d.ts',
        'web/src/locales/zh-CN/pages/crm-tickets.ts',
        'web/src/locales/en-US/pages/crm-tickets.ts',
    );

    $migration = collect($files)->first(fn (string $content, string $path): bool => str_ends_with($path, '_create_tickets_table.php'));
    expect($migration)
        ->toContain("\$table->string('title', 100);")
        ->toContain("\$table->text('body')->nullable();")
        ->toContain("\$table->foreignId('category_id')->constrained('article_categories')->restrictOnDelete();")
        ->toContain("\$table->string('status', 20)->default('active')->index();")
        ->toContain("\$table->foreignId('department_id')->nullable()->index();")
        ->toContain('$table->softDeletes();');

    expect($files['server/app/Models/Ticket.php'])
        ->toContain('use HasDataScope, HasFactory, SoftDeletes, StoresDatesInAppTimezone;')
        ->toContain("'status' => Status::class,")
        ->toContain("'meta' => 'array',")
        ->toContain('public function category(): BelongsTo')
        ->not->toContain('use App\Models\ArticleCategory;');
    expect($files['server/database/factories/TicketFactory.php'])->toContain('use App\Models\ArticleCategory;');

    expect($files['server/app/Http/Requests/Crm/StoreTicketRequest.php'])
        ->toContain("'title' => ['required', 'string', 'max:100'],")
        ->toContain("'category_id' => ['required', 'integer', Rule::exists('article_categories', 'id')],")
        ->toContain("'source' => ['required', 'string', new InDict('article_source')],")
        ->toContain("'level' => ['required', 'string', 'max:255', 'in:low,high'],")
        ->toContain('use App\Rules\InDict;');

    expect($files['server/app/Http/Controllers/Api/Crm/TicketController.php'])->toContain('->visibleTo($actor)');
    expect($files['server/app/Policies/TicketPolicy.php'])->toContain('isVisibleTo($actor)');

    expect($files['server/config/permissions.php'])
        ->toContain("'code' => 'crm',")
        ->toContain("'code' => 'crm.ticket',")
        ->toContain("'name' => 'Tickets',")
        ->toContain("'path' => '/crm/tickets',")
        ->toContain("'type' => 'directory',\n        'path' => '/crm',\n        'icon' => 'AppstoreOutlined',")
        ->toContain('// edge:permissions:crm');
    expect($files['server/routes/api.php'])
        ->toContain("Route::apiResource('tickets', TicketController::class)")
        ->toContain("->middlewareFor('store', 'can:crm.ticket.create')")
        ->toContain('use App\Http\Controllers\Api\Crm\TicketController;');
    expect($files['server/lang/zh_CN/operations.php'])->toContain("'tickets.store' => '新增Ticket',");
    expect($files['server/lang/en/operations.php'])->toContain("'tickets.destroy' => 'Delete Ticket',");

    expect($files['web/config/routes.ts'])->toContain("path: '/crm/tickets',")->toContain("locale: 'permission.crm.ticket',")->toContain('// edge:routes:crm');
    expect($files['web/src/access.ts'])->toContain("| 'crm.ticket.delete'");
    expect(array_keys($files))->not->toContain('web/src/locales/zh-CN/menu.ts');
    expect($files['web/src/locales/en-US.ts'])->toContain("import crmTickets from './en-US/pages/crm-tickets';")->toContain('...crmTickets,');
    expect($files['web/src/pages/crm/tickets/data.d.ts'])
        ->toContain('starts_on: string | null;')
        ->toContain('status: API.Status;')
        ->toContain('department_id: API.Id | null;');

    // 前端：表单拆到 components/，导入按实际使用的控件生成（无未使用导入），字段规则下发为前端校验
    $index = $files['web/src/pages/crm/tickets/index.tsx'];
    $form = $files['web/src/pages/crm/tickets/components/TicketForm.tsx'];
    expect($index)
        ->toContain("import TicketForm from './components/TicketForm';")
        ->toContain("import { booleanTransform, tableRequest } from '@/utils/table';")
        ->toContain("request: () => fetchSelectOptions('article-categories'),")
        ->toContain("dataIndex: 'department_id',")
        ->not->toContain('ProFormText')
        ->not->toContain('{{@import')
        ->not->toContain('{{ imports }}');
    expect($form)
        ->toContain("import type { Ticket, TicketFormValues } from '../data';")
        ->toContain('toOffsetDateTime,')
        ->toContain("fieldProps={{ maxLength: 100 }}\n        rules={[{ required: true }]}")
        ->toContain("request={() => fetchSelectOptions('article-categories')}")
        ->toContain('transform={(value: unknown) => ({ starts_on: value ?? null })}')
        ->not->toContain('PageContainer')
        ->not->toContain('booleanTransform');
    expect(substr_count($form, "from '@ant-design/pro-components';"))->toBe(2);

    expect($files['server/tests/Feature/TicketTest.php'])
        ->toContain("->assertJsonPath('data.published_at', CarbonImmutable::parse('2026-01-01T08:00:00+00:00')")
        ->toContain("\$this->assertSoftDeleted('tickets', ['id' => \$id]);");

    foreach ($files as $path => $content) {
        if (str_ends_with($path, '.php')) {
            expect(Process::run(['php', '-l', "{$this->root}/{$path}"])->successful())->toBeTrue("{$path} 存在语法错误");
        }

        expect($content)->not->toMatch('/\{\{\s*[a-z_.]+\s*\}\}/');
    }
});

test('the generated menu node shows up in the dynamic menu after permission:sync', function () {
    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'crm', '--fields' => 'name:string'])->assertSuccessful();

    config()->set('permissions', require "{$this->base}/config/permissions.php");
    $this->artisan('permission:sync')->assertSuccessful();
    actingAsUser(superAdmin());

    $crm = collect($this->getJson('/api/auth/menus')->assertOk()->json('data'))->firstWhere('code', 'crm');

    expect($crm)->toMatchArray(['type' => 'directory', 'path' => '/crm', 'icon' => 'AppstoreOutlined'])
        ->and($crm['children'][0])->toMatchArray(['code' => 'crm.ticket', 'type' => 'menu', 'path' => '/crm/tickets', 'name' => 'Tickets']);
});

test('--with-options generates an option endpoint and foreign fields without one get a TODO', function () {
    $this->artisan('edge:make-module', ['name' => 'Tag', '--module' => 'content', '--fields' => 'name:string,sort:integer,status:status', '--with-options' => true])
        ->assertSuccessful();
    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'crm', '--fields' => 'name:string,tag_id:foreign:tags,owner_id:foreign:accounts:nullable'])
        ->expectsOutputToContain('外键 owner_id 没有可用的选项接口 /api/options/accounts')
        ->assertSuccessful();

    $files = generatedFiles($this->root);

    expect($files['server/app/Http/Controllers/Api/Options/TagOptionController.php'])
        ->toContain("->where('status', Status::Active)")
        ->toContain("->orderBy('sort')")
        ->toContain('use App\Enums\Status;');
    expect($files['server/routes/api.php'])
        ->toContain("Route::get('tags', TagOptionController::class)->name('tags');\n        // edge:options")
        ->toContain('use App\Http\Controllers\Api\Options\TagOptionController;');
    expect($files['server/tests/Feature/TagTest.php'])->toContain("test('tag options can be listed by any user'");

    expect($files['web/src/pages/crm/tickets/components/TicketForm.tsx'])
        ->toContain("request={() => fetchSelectOptions('tags')}")
        ->toContain('// TODO 后端没有 /api/options/accounts')
        ->toContain('request={async () => []}');
    expect($files['web/src/locales/en-US/pages/crm-tickets.ts'])->toContain("'pages.crm.tickets.ownerId': 'Owner',");
});

test('--with-options requires a name field', function () {
    $this->artisan('edge:make-module', ['name' => 'Tag', '--module' => 'content', '--fields' => 'title:string', '--with-options' => true])
        ->expectsOutputToContain('--with-options 需要模块包含 name 字段')
        ->assertFailed();
});

test('conditional blocks are evaluated from the inside out and drop standalone tag lines', function () {
    $template = "a\n{{#if outer}}\nb {{#unless inner}}c{{else}}d{{/unless}}\n{{/if}}\n{{#if missing}}\ne\n{{/if}}\nf";

    expect(StubRenderer::conditionals($template, ['outer' => 'true', 'inner' => '']))->toBe("a\nb c\nf")
        ->and(StubRenderer::conditionals($template, ['outer' => 'true', 'inner' => 'true']))->toBe("a\nb d\nf")
        ->and(StubRenderer::conditionals($template, ['outer' => null]))->toBe("a\nf");
});

test('typescript import directives are merged, deduplicated and sorted', function () {
    $output = StubRenderer::organizeTsImports(implode("\n", [
        "{{@import { useState } from 'react'}}",
        "{{@import type React from 'react'}}",
        '{{ imports }}',
        '',
        "{{@import { b } from './local'}}",
        "{{@import { useRef, useState } from 'react'}}",
        "{{@import { z } from '@/utils/z'}}",
        'const x = 1;',
    ]));

    expect($output)->toBe(implode("\n", [
        "import type React from 'react';",
        "import { useRef, useState } from 'react';",
        "import { z } from '@/utils/z';",
        "import { b } from './local';",
        '',
        'const x = 1;',
    ]));
});

test('running the generator twice does not duplicate anything', function () {
    $arguments = ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string,status:status'];

    $this->artisan('edge:make-module', $arguments)->assertSuccessful();
    $first = generatedFiles($this->root);

    $this->artisan('edge:make-module', $arguments)->expectsOutputToContain('没有需要写入的文件')->assertSuccessful();

    expect(generatedFiles($this->root))->toBe($first)
        ->and(substr_count($first['server/routes/api.php'], "Route::apiResource('tickets'"))->toBe(1)
        ->and(substr_count($first['server/config/permissions.php'], "'code' => 'content.ticket'"))->toBe(1)
        ->and(substr_count($first['server/config/permissions.php'], "'code' => 'content',"))->toBe(1);
});

test('--force overwrites generated files while registrations stay unique', function () {
    $arguments = ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string'];
    $this->artisan('edge:make-module', $arguments)->assertSuccessful();
    File::put("{$this->base}/app/Models/Ticket.php", 'changed');

    $this->artisan('edge:make-module', [...$arguments, '--force' => true])->assertSuccessful();

    expect(File::get("{$this->base}/app/Models/Ticket.php"))->toContain('class Ticket extends Model')
        ->and(substr_count(File::get("{$this->base}/routes/api.php"), "Route::apiResource('tickets'"))->toBe(1)
        ->and(File::glob("{$this->base}/database/migrations/*_create_tickets_table.php"))->toHaveCount(1);
});

test('--dry-run writes nothing', function () {
    $before = generatedFiles($this->root);

    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string', '--dry-run' => true])
        ->expectsOutputToContain('app/Models/Ticket.php')
        ->assertSuccessful();

    expect(generatedFiles($this->root))->toBe($before);
});

test('frontend generation can be disabled or is skipped when the web directory is missing', function () {
    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string', '--no-web' => true])->assertSuccessful();
    expect(File::exists("{$this->web}/src/pages/content/tickets/index.tsx"))->toBeFalse();

    config(['edge.generator.web_path' => "{$this->root}/missing-web"]);
    $this->artisan('edge:make-module', ['name' => 'Coupon', '--module' => 'content', '--fields' => 'name:string'])
        ->expectsOutputToContain('不存在，已跳过前端代码')
        ->assertSuccessful();
    expect(File::exists("{$this->base}/app/Models/Coupon.php"))->toBeTrue();
});

test('missing anchors are reported instead of guessed', function () {
    File::put("{$this->web}/src/access.ts", "export type PermissionCode = 'dashboard.view';\n");

    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string'])
        ->expectsOutputToContain('未找到锚点 // edge:permission-codes')
        ->assertSuccessful();

    expect(File::get("{$this->web}/src/access.ts"))->toBe("export type PermissionCode = 'dashboard.view';\n");
});

test('stubs from an earlier stub path take precedence', function () {
    $custom = "{$this->root}/custom-stubs";
    File::ensureDirectoryExists("{$custom}/fragments");
    File::put("{$custom}/fragments/rules.string.stub", "            '{{ field.name }}' => ['custom', 'rule'],\n");
    config(['edge.generator.stub_paths' => [$custom, base_path('stubs/edge')]]);

    $this->artisan('edge:make-module', ['name' => 'Ticket', '--module' => 'content', '--fields' => 'name:string,quantity:integer', '--no-web' => true])->assertSuccessful();

    expect(File::get("{$this->base}/app/Http/Requests/Content/StoreTicketRequest.php"))
        ->toContain("'name' => ['custom', 'rule'],")
        ->toContain("'quantity' => ['required', 'integer'],");
});

test('invalid definitions fail with a message', function (array $arguments, string $message) {
    $this->artisan('edge:make-module', ['name' => 'Ticket', ...$arguments])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(File::exists("{$this->base}/app/Models/Ticket.php"))->toBeFalse();
})->with([
    'missing module' => [['--fields' => 'name:string'], '--module'],
    'missing fields' => [['--module' => 'content'], '--fields'],
    'unknown type' => [['--module' => 'content', '--fields' => 'name:uuid'], '类型 [uuid] 不支持'],
    'foreign without table' => [['--module' => 'content', '--fields' => 'user_id:foreign'], '需要参数'],
    'reserved field' => [['--module' => 'content', '--fields' => 'created_by:integer'], '自动维护'],
    'bad module' => [['--module' => 'Content', '--fields' => 'name:string'], '--module'],
]);

test('the field DSL keeps commas inside rules', function () {
    $fields = FieldParser::parse('level:string:required|in:low,mid,high,owner_id:foreign:users:nullable');

    expect($fields)->toHaveCount(2)
        ->and($fields[0]->rules)->toBe(['required', 'in:low,mid,high'])
        ->and($fields[1]->argument)->toBe('users')
        ->and($fields[1]->isNullable())->toBeTrue();
});
