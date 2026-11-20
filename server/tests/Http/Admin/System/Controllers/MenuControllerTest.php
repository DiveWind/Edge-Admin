<?php

use App\Core\System\Enums\{PermissionType, Status};
use App\Core\System\Models\{Permission, Role, User};

/*
|--------------------------------------------------------------------------
| 菜单管理：/system/menus 的整棵树、详情、新增、修改、删除（设计文档 §6.5、§8.2、§8.6）
|--------------------------------------------------------------------------
|
| 节点来自 System 的内置声明（core:sync-permissions）：system → system.user / system.role / system.menu 及其按钮。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

/**
 * 节点的 id。
 */
function menuId(string $name): int
{
    return Permission::query()->where('name', $name)->sole()->id;
}

/**
 * 某类型的合法提交（新增在根上，按钮挂在 system.user 下），$overrides 逐键覆盖（null 表示不提交该值）。
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function menuPayload(string $type, array $overrides = []): array
{
    $payload = match ($type) {
        'catalog' => ['type' => 'catalog', 'name' => 'demo', 'path' => '/demo', 'meta' => ['title' => 'demo.title']],
        'menu' => ['type' => 'menu', 'name' => 'demo', 'path' => '/demo', 'component' => '/demo/index', 'meta' => ['title' => 'demo.title']],
        'embedded' => ['type' => 'embedded', 'name' => 'demo', 'path' => '/demo', 'meta' => ['title' => 'demo.title', 'iframeSrc' => 'https://example.com/embed']],
        'link' => ['type' => 'link', 'name' => 'demo', 'path' => '/demo', 'meta' => ['title' => 'demo.title', 'link' => 'https://example.com/docs']],
        'button' => ['type' => 'button', 'name' => 'system.user.import', 'parent_id' => menuId('system.user'), 'meta' => ['title' => 'demo.export']],
    };

    return array_replace_recursive($payload, $overrides);
}

/**
 * 节点当前的提交（修改时照原样提交，再按 $overrides 覆盖）：与前端编辑表单的回填一致。
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function menuPayloadOf(Permission $node, array $overrides = []): array
{
    $node->refresh();

    return array_replace_recursive([
        'parent_id' => $node->parent_id,
        'type' => $node->type->value,
        'name' => $node->name,
        'path' => $node->path,
        'component' => $node->component,
        'status' => $node->status->value,
        'meta' => ['title' => $node->title, 'icon' => $node->icon, 'order' => $node->sort, ...($node->meta ?? [])],
    ], $overrides);
}

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/system/menus')->assertUnauthorized();
    });

    it('returns 403 without the system.menu code', function (): void {
        $this->actingAs(userWithPermissions('system.user'))
            ->getJson('/api/admin/system/menus')
            ->assertForbidden()
            ->assertExactJson(['code' => 403, 'data' => null, 'message' => 'This action is unauthorized.']);
    });

    it('returns the whole tree, including disabled nodes and buttons, ordered by sort and id', function (): void {
        Permission::query()->where('name', 'system.user')->sole()->update(['status' => Status::Disabled]);
        $demo = Permission::factory()->create(['name' => 'demo', 'path' => '/demo', 'title' => 'demo.title', 'sort' => 1]);
        Permission::factory()->create(['name' => 'other', 'sort' => 1]);

        $response = $this->actingAs(userWithPermissions('system.menu'))
            ->getJson('/api/admin/system/menus')
            ->assertOk();

        // 业务模块（app/Modules）的目录也在树中：只检查这里创建的与 Core 的目录的相对顺序，测试不依赖有哪些业务模块
        $system = treeIndex($response->json('data'), 'system');

        expect(coreTreeNames($response->json('data.*.name'), ['demo', 'other']))->toBe(['demo', 'other', 'system', 'monitor', 'message']);

        $response->assertJsonPath("data.{$system}.children.*.name", ['system.user', 'system.role', 'system.menu', 'system.dict', 'system.setting', 'system.attachment'])
            ->assertJsonPath("data.{$system}.children.0.status", 'disabled')
            ->assertJsonPath("data.{$system}.children.0.children.*.name", [
                'system.user.create', 'system.user.update', 'system.user.delete', 'system.user.status', 'system.user.password', 'system.user.export',
            ])
            ->assertJsonPath("data.{$system}.children.0.children.0.children", [])
            ->assertJsonPath('data.0.id', $demo->id);
    });

    it('outputs the node fields with the Vben meta', function (): void {
        $catalog = Permission::factory()->create(['name' => 'demo', 'path' => '/demo', 'title' => 'demo.title', 'icon' => 'lucide:box', 'sort' => -1]);
        $page = Permission::factory()->childOf($catalog)->embedded('https://example.com/embed')->disabled()->create([
            'name' => 'demo.page', 'path' => '/demo/page', 'title' => 'Page', 'sort' => 3,
            'meta' => ['iframeSrc' => 'https://example.com/embed', 'hideInMenu' => true],
        ]);

        $this->actingAs(superAdminActor())
            ->getJson('/api/admin/system/menus')
            ->assertOk()
            ->assertJsonPath('data.0', [
                'id' => $catalog->id,
                'parent_id' => null,
                'type' => 'catalog',
                'name' => 'demo',
                'path' => '/demo',
                'component' => null,
                'status' => 'enabled',
                'is_builtin' => false,
                'meta' => ['title' => 'demo.title', 'icon' => 'lucide:box', 'order' => -1],
                'children' => [[
                    'id' => $page->id,
                    'parent_id' => $catalog->id,
                    'type' => 'embedded',
                    'name' => 'demo.page',
                    'path' => '/demo/page',
                    'component' => null,
                    'status' => 'disabled',
                    'is_builtin' => false,
                    'meta' => ['title' => 'Page', 'icon' => null, 'order' => 3, 'iframeSrc' => 'https://example.com/embed', 'hideInMenu' => true],
                    'children' => [],
                ]],
            ])
            ->assertJsonPath('data.1.is_builtin', true);
    });
});

describe('show', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/system/menus/1')->assertUnauthorized();
    });

    it('returns 403 without the system.menu code', function (): void {
        $this->actingAs(userWithPermissions('system.user'))
            ->getJson('/api/admin/system/menus/'.menuId('system.user'))
            ->assertForbidden();
    });

    it('returns the node without children', function (): void {
        $this->actingAs(userWithPermissions('system.menu'))
            ->getJson('/api/admin/system/menus/'.menuId('system.user'))
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => [
                'id' => menuId('system.user'),
                'parent_id' => menuId('system'),
                'type' => 'menu',
                'name' => 'system.user',
                'path' => '/system/user',
                'component' => '/system/user/list',
                'status' => 'enabled',
                'is_builtin' => true,
                'meta' => ['title' => 'system.user.title', 'icon' => 'lucide:user', 'order' => 10],
            ]]);
    });

    it('returns 404 for unknown and malformed ids', function (string $id): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/menus/{$id}")
            ->assertNotFound()
            ->assertJsonPath('code', 404);
    })->with(['missing' => '999999', 'not a number' => 'abc']);
});

describe('store', function (): void {
    it('returns 401 for guests', function (): void {
        $this->postJson('/api/admin/system/menus', menuPayload('catalog'))->assertUnauthorized();
    });

    it('returns 403 without the system.menu.create code', function (): void {
        $this->actingAs(userWithPermissions('system.menu', 'system.menu.update'))
            ->postJson('/api/admin/system/menus', menuPayload('catalog'))
            ->assertForbidden();

        $this->assertDatabaseMissing('permissions', ['name' => 'demo']);
    });

    it('creates a custom node of each type', function (string $type, array $columns): void {
        $response = $this->actingAs(userWithPermissions('system.menu.create'))
            ->postJson('/api/admin/system/menus', menuPayload($type));

        $node = Permission::query()->where('type', $type)->where('is_builtin', false)->sole();

        $response->assertCreated()
            ->assertJsonPath('data.id', $node->id)
            ->assertJsonPath('data.type', $type)
            ->assertJsonPath('data.is_builtin', false)
            ->assertJsonMissingPath('data.children');
        $this->assertDatabaseHas('permissions', ['id' => $node->id, 'guard_name' => 'web', 'status' => 'enabled', 'sort' => 0, 'icon' => null, ...$columns]);
        expect($node->meta)->toBe($type === 'embedded' ? ['iframeSrc' => 'https://example.com/embed'] : ($type === 'link' ? ['link' => 'https://example.com/docs'] : null));
    })->with([
        'catalog' => ['catalog', ['name' => 'demo', 'parent_id' => null, 'path' => '/demo', 'component' => null, 'title' => 'demo.title']],
        'menu' => ['menu', ['name' => 'demo', 'parent_id' => null, 'path' => '/demo', 'component' => '/demo/index']],
        'embedded' => ['embedded', ['name' => 'demo', 'path' => '/demo', 'component' => null]],
        'link' => ['link', ['name' => 'demo', 'path' => '/demo', 'component' => null]],
        'button' => ['button', ['name' => 'system.user.import', 'path' => null, 'component' => null, 'title' => 'demo.export']],
    ]);

    it('stores meta.title, meta.icon and meta.order in their columns and the other meta keys in the meta column', function (): void {
        $response = $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/menus', menuPayload('menu', [
                'parent_id' => menuId('system'),
                'status' => 'disabled',
                'meta' => [
                    'title' => 'Demo', 'icon' => 'lucide:box', 'order' => 15,
                    'activePath' => '/demo', 'activeIcon' => 'lucide:box-select', 'keepAlive' => true, 'affixTab' => 0,
                    'hideInMenu' => '1', 'hideChildrenInMenu' => false, 'hideInBreadcrumb' => null, 'hideInTab' => false,
                    'badge' => 'new', 'badgeType' => 'normal', 'badgeVariants' => 'destructive',
                ],
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', menuId('system'))
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonPath('data.meta.title', 'Demo')
            ->assertJsonPath('data.meta.order', 15)
            ->assertJsonPath('data.meta.keepAlive', true);
        expect(Permission::query()->where('name', 'demo')->sole())
            ->title->toBe('Demo')
            ->icon->toBe('lucide:box')
            ->sort->toBe(15)
            ->meta->toBe([
                'activePath' => '/demo', 'activeIcon' => 'lucide:box-select', 'keepAlive' => true, 'affixTab' => false,
                'hideInMenu' => true, 'hideChildrenInMenu' => false, 'hideInTab' => false,
                'badge' => 'new', 'badgeType' => 'normal', 'badgeVariants' => 'destructive',
            ]);
    });

    it('requires type, name and meta.title', function (): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/menus', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'name', 'meta', 'meta.title']);
    });

    it('rejects fields that the type requires or does not allow', function (string $type, array $overrides, string $field): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/menus', menuPayload($type, $overrides))
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$field]);

        expect(Permission::query()->where('is_builtin', false)->exists())->toBeFalse();
    })->with([
        'catalog without path' => ['catalog', ['path' => null], 'path'],
        'catalog with component' => ['catalog', ['component' => '/demo/index'], 'component'],
        'catalog with meta.link' => ['catalog', ['meta' => ['link' => 'https://example.com']], 'meta.link'],
        'menu without path' => ['menu', ['path' => null], 'path'],
        'menu without component' => ['menu', ['component' => null], 'component'],
        'menu with meta.iframeSrc' => ['menu', ['meta' => ['iframeSrc' => 'https://example.com']], 'meta.iframeSrc'],
        'embedded without meta.iframeSrc' => ['embedded', ['meta' => ['iframeSrc' => null]], 'meta.iframeSrc'],
        'embedded with component' => ['embedded', ['component' => '/demo/index'], 'component'],
        'embedded with meta.link' => ['embedded', ['meta' => ['link' => 'https://example.com']], 'meta.link'],
        'link without meta.link' => ['link', ['meta' => ['link' => null]], 'meta.link'],
        'link with meta.iframeSrc' => ['link', ['meta' => ['iframeSrc' => 'https://example.com']], 'meta.iframeSrc'],
        'button with path' => ['button', ['path' => '/export'], 'path'],
        'button with component' => ['button', ['component' => '/export'], 'component'],
        'button with route meta' => ['button', ['meta' => ['hideInMenu' => true]], 'meta.hideInMenu'],
    ]);

    it('rejects invalid values', function (array $overrides, string $field): void {
        Permission::factory()->create(['name' => 'taken', 'path' => '/taken']);

        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/menus', menuPayload('menu', $overrides))
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$field]);

        $this->assertDatabaseMissing('permissions', ['name' => 'demo']);
    })->with([
        'unknown type' => [['type' => 'page'], 'type'],
        'upper-case name' => [['name' => 'Demo'], 'name'],
        'name starting with a digit' => [['name' => '1demo'], 'name'],
        'name with an empty segment' => [['name' => 'demo..page'], 'name'],
        'name taken' => [['name' => 'taken'], 'name'],
        'built-in name taken' => [['name' => 'system.user'], 'name'],
        'path without a slash' => [['path' => 'demo'], 'path'],
        'path taken' => [['path' => '/taken'], 'path'],
        'component without a slash' => [['component' => 'demo/index'], 'component'],
        'unknown parent' => [['parent_id' => 999999], 'parent_id'],
        'unknown status' => [['status' => 'active'], 'status'],
        'empty title' => [['meta' => ['title' => '']], 'meta.title'],
        'order not an integer' => [['meta' => ['order' => 'first']], 'meta.order'],
        'boolean meta not a boolean' => [['meta' => ['keepAlive' => 'yes']], 'meta.keepAlive'],
        'unknown badge type' => [['meta' => ['badgeType' => 'big']], 'meta.badgeType'],
        'unknown meta key' => [['meta' => ['redirect' => '/x']], 'meta.redirect'],
        'top-level sort' => [['sort' => 5], 'sort'],
        'top-level title' => [['title' => 'Demo'], 'title'],
        'is_builtin' => [['is_builtin' => true], 'is_builtin'],
    ]);

    it('only accepts http and https URLs', function (string $type, string $key): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/menus', menuPayload($type, ['meta' => [$key => 'javascript:alert(1)']]))
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(["meta.{$key}"]);
    })->with([
        'embedded' => ['embedded', 'iframeSrc'],
        'link' => ['link', 'link'],
    ]);

    it('localizes the validation messages', function (): void {
        $errors = $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/menus', menuPayload('embedded', ['name' => 'Demo', 'meta' => ['iframeSrc' => null, 'redirect' => '/x']]), ['Accept-Language' => 'zh-CN'])
            ->assertUnprocessable()
            ->json('errors');

        expect($errors)->toBe([
            'name' => ['权限码须为以字母开头、以点分隔的小写字符，如 system.user。'],
            'meta.iframeSrc' => ['内嵌页地址 不能为空。'],
            'meta.redirect' => ['不允许提交 meta.redirect。'],
        ]);
    });

    it('rejects a parent that cannot contain the type', function (string $type, ?string $parent, string $message): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/menus', menuPayload($type, ['name' => 'demo', 'parent_id' => $parent === null ? null : menuId($parent)]))
            ->assertUnprocessable()
            ->assertExactJson(['code' => 422, 'data' => null, 'message' => $message, 'errors' => ['parent_id' => [$message]]]);

        $this->assertDatabaseMissing('permissions', ['name' => 'demo']);
    })->with([
        'button at the root' => ['button', null, 'Buttons can only be placed under a menu.'],
        'button under a catalog' => ['button', 'system', 'Buttons can only be placed under a menu.'],
        'button under a button' => ['button', 'system.user.create', 'Buttons can only be placed under a menu.'],
        'menu under a menu' => ['menu', 'system.user', 'Catalogs, menus, embedded pages and links can only be placed at the root or under a catalog.'],
        'catalog under a button' => ['catalog', 'system.user.create', 'Catalogs, menus, embedded pages and links can only be placed at the root or under a catalog.'],
    ]);

    it('shows the new node in /menu/all and /auth/codes right away', function (): void {
        $actor = superAdminActor();

        $this->actingAs($actor)
            ->postJson('/api/admin/system/menus', menuPayload('menu', ['parent_id' => menuId('system')]))
            ->assertCreated();

        $this->actingAs($actor)->getJson('/api/admin/auth/codes')->assertJsonFragment(['demo']);
        $menus = $this->actingAs($actor)->getJson('/api/admin/menu/all');
        $menus->assertJsonPath('data.'.treeIndex($menus->json('data'), 'system').'.children.*.name', ['demo', 'system.user', 'system.role', 'system.menu', 'system.dict', 'system.setting', 'system.attachment']);
    });
});

describe('update', function (): void {
    beforeEach(function (): void {
        $this->catalog = Permission::factory()->create(['name' => 'demo', 'path' => '/demo', 'title' => 'demo.title']);
        $this->page = Permission::factory()->childOf($this->catalog)->menu()->create(['name' => 'demo.page', 'path' => '/demo/page']);
        $this->button = Permission::factory()->childOf($this->page)->button()->create(['name' => 'demo.page.create']);
    });

    it('returns 401 for guests', function (): void {
        $this->putJson("/api/admin/system/menus/{$this->catalog->id}", menuPayloadOf($this->catalog))->assertUnauthorized();
    });

    it('returns 403 without the system.menu.update code', function (): void {
        $this->actingAs(userWithPermissions('system.menu', 'system.menu.create'))
            ->putJson("/api/admin/system/menus/{$this->catalog->id}", menuPayloadOf($this->catalog, ['meta' => ['title' => 'Changed']]))
            ->assertForbidden();

        expect($this->catalog->fresh()?->title)->toBe('demo.title');
    });

    it('updates a custom node', function (): void {
        $this->actingAs(userWithPermissions('system.menu.update'))
            ->putJson("/api/admin/system/menus/{$this->page->id}", [
                'type' => 'menu', 'name' => 'demo.list', 'path' => '/demo/list', 'component' => '/demo/list/index',
                'parent_id' => null, 'status' => 'disabled', 'meta' => ['title' => 'List', 'icon' => 'lucide:list', 'order' => 7, 'keepAlive' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'demo.list')
            ->assertJsonPath('data.parent_id', null)
            ->assertJsonMissingPath('data.children');

        expect($this->page->fresh())
            ->name->toBe('demo.list')
            ->parent_id->toBeNull()
            ->path->toBe('/demo/list')
            ->component->toBe('/demo/list/index')
            ->status->toBe(Status::Disabled)
            ->title->toBe('List')
            ->icon->toBe('lucide:list')
            ->sort->toBe(7)
            ->meta->toBe(['keepAlive' => true]);
    });

    it('keeps the parent and status when they are not submitted and replaces meta as a whole', function (): void {
        $this->page->forceFill(['status' => Status::Disabled, 'icon' => 'lucide:box', 'sort' => 5, 'meta' => ['keepAlive' => true]])->save();

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$this->page->id}", [
                'type' => 'menu', 'name' => 'demo.page', 'path' => '/demo/page', 'component' => '/demo/page/index', 'meta' => ['title' => 'Page'],
            ])
            ->assertOk();

        expect($this->page->fresh())
            ->parent_id->toBe($this->catalog->id)
            ->status->toBe(Status::Disabled)
            ->icon->toBeNull()
            ->sort->toBe(0)
            ->meta->toBeNull();
    });

    it('keeps its own name and path', function (): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$this->page->id}", menuPayloadOf($this->page))
            ->assertOk();
    });

    it('rejects the name or path of another node', function (string $field, string $value): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$this->page->id}", menuPayloadOf($this->page, [$field => $value]))
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$field]);
    })->with([
        'name' => ['name', 'demo'],
        'path' => ['path', '/demo'],
    ]);

    it('rejects moving a node under itself or its descendants', function (string $parent): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$this->catalog->id}", menuPayloadOf($this->catalog, ['parent_id' => menuId($parent)]))
            ->assertUnprocessable()
            ->assertExactJson(['code' => 422, 'data' => null, 'message' => 'A node cannot be placed under itself or its descendants.', 'errors' => [
                'parent_id' => ['A node cannot be placed under itself or its descendants.'],
            ]]);

        expect($this->catalog->fresh()?->parent_id)->toBeNull();
    })->with(['itself' => 'demo', 'a child' => 'demo.page', 'a grandchild' => 'demo.page.create']);

    it('rejects a parent that cannot contain the node', function (): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$this->page->id}", menuPayloadOf($this->page, ['parent_id' => menuId('system.user')]))
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['parent_id']);
    });

    it('rejects a type that cannot be placed under the current parent', function (): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$this->button->id}", menuPayloadOf($this->button, ['type' => 'catalog', 'path' => '/demo/page/create']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', 'Catalogs, menus, embedded pages and links can only be placed at the root or under a catalog.');

        expect($this->button->fresh()?->type)->toBe(PermissionType::Button);
    });

    it('rejects a type that cannot contain the existing children', function (Closure $node, array $overrides): void {
        $node = $node();

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$node->id}", menuPayloadOf($node, $overrides))
            ->assertUnprocessable()
            ->assertExactJson(['code' => 422, 'data' => null, 'message' => 'The new type cannot contain the existing child nodes.', 'errors' => [
                'type' => ['The new type cannot contain the existing child nodes.'],
            ]]);
    })->with([
        'menu with buttons to catalog' => [fn () => test()->page, ['type' => 'catalog', 'component' => null]],
        'catalog with menus to menu' => [fn () => test()->catalog, ['type' => 'menu', 'component' => '/demo/index']],
    ]);

    it('changes the type of a node without children', function (): void {
        $leaf = Permission::factory()->create();

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$leaf->id}", menuPayloadOf($leaf, ['type' => 'link', 'meta' => ['link' => 'https://example.com']]))
            ->assertOk()
            ->assertJsonPath('data.type', 'link')
            ->assertJsonPath('data.meta.link', 'https://example.com');
    });

    describe('built-in nodes', function (): void {
        it('updates the title, icon, order, status, parent and other meta keys', function (): void {
            $node = Permission::query()->where('name', 'system.role')->sole();

            $this->actingAs(superAdminActor())
                ->putJson("/api/admin/system/menus/{$node->id}", menuPayloadOf($node, [
                    'parent_id' => $this->catalog->id, 'status' => 'disabled', 'meta' => ['title' => 'Roles', 'icon' => 'lucide:users', 'order' => 1, 'hideInMenu' => true],
                ]))
                ->assertOk()
                ->assertJsonPath('data.is_builtin', true);

            expect($node->fresh())
                ->parent_id->toBe($this->catalog->id)
                ->status->toBe(Status::Disabled)
                ->title->toBe('Roles')
                ->icon->toBe('lucide:users')
                ->sort->toBe(1)
                ->meta->toBe(['hideInMenu' => true]);
        });

        it('rejects changes to the structural fields', function (array $overrides, string $field, string $message): void {
            $node = Permission::query()->where('name', 'system.role')->sole();

            $this->actingAs(superAdminActor())
                ->putJson("/api/admin/system/menus/{$node->id}", menuPayloadOf($node, $overrides))
                ->assertUnprocessable()
                ->assertJsonPath("errors.{$field}.0", $message);

            expect($node->fresh())
                ->name->toBe('system.role')
                ->type->toBe(PermissionType::Menu)
                ->path->toBe('/system/role')
                ->component->toBe('/system/role/list');
        })->with([
            'name' => [['name' => 'system.roles'], 'name', 'The permission code of a built-in node cannot be changed.'],
            'type' => [['type' => 'catalog', 'component' => null], 'type', 'The type of a built-in node cannot be changed.'],
            'path' => [['path' => '/system/roles'], 'path', 'The path of a built-in node cannot be changed.'],
            'component' => [['component' => '/system/role/index'], 'component', 'The component of a built-in node cannot be changed.'],
        ]);

        it('rejects changes to the URL of built-in embedded pages and links', function (): void {
            $node = Permission::factory()->link('https://example.com/docs')->builtin()->create();

            $this->actingAs(superAdminActor())
                ->putJson("/api/admin/system/menus/{$node->id}", menuPayloadOf($node, ['meta' => ['link' => 'https://example.com/v2']]), ['Accept-Language' => 'zh-CN'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['meta.link' => '内置节点的地址不能修改。']);

            expect($node->fresh()?->meta)->toBe(['link' => 'https://example.com/docs']);
        });
    });

    describe('menu management lifeline', function (): void {
        it('rejects disabling the menu management nodes and their ancestors', function (string $name): void {
            $node = Permission::query()->where('name', $name)->sole();

            $this->actingAs(superAdminActor())
                ->putJson("/api/admin/system/menus/{$node->id}", menuPayloadOf($node, ['status' => 'disabled']))
                ->assertUnprocessable()
                ->assertExactJson(['code' => 422, 'data' => null, 'message' => 'The menu management nodes and their ancestors cannot be disabled.', 'errors' => [
                    'status' => ['The menu management nodes and their ancestors cannot be disabled.'],
                ]]);

            expect($node->fresh()?->status)->toBe(Status::Enabled);
        })->with(['system.menu', 'system.menu.update', 'system']);

        it('allows disabling other nodes', function (string $name): void {
            $node = Permission::query()->where('name', $name)->sole();

            $this->actingAs(superAdminActor())
                ->putJson("/api/admin/system/menus/{$node->id}", menuPayloadOf($node, ['status' => 'disabled']))
                ->assertOk()
                ->assertJsonPath('data.status', 'disabled');
        })->with(['system.menu.create', 'system.menu.delete', 'system.user']);

        it('follows the ancestors after the menu management node is moved', function (): void {
            $menu = Permission::query()->where('name', 'system.menu')->sole();
            $system = Permission::query()->where('name', 'system')->sole();
            $actor = superAdminActor();

            $this->actingAs($actor)
                ->putJson("/api/admin/system/menus/{$menu->id}", menuPayloadOf($menu, ['parent_id' => $this->catalog->id]))
                ->assertOk();

            $this->actingAs($actor)
                ->putJson("/api/admin/system/menus/{$this->catalog->id}", menuPayloadOf($this->catalog, ['status' => 'disabled']))
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors(['status']);

            $this->actingAs($actor)
                ->putJson("/api/admin/system/menus/{$system->id}", menuPayloadOf($system, ['status' => 'disabled']))
                ->assertOk();
        });

        it('rejects moving the menu management nodes under a disabled node', function (string $name): void {
            $disabled = Permission::factory()->disabled()->create();
            $node = Permission::query()->where('name', $name)->sole();
            $parentId = $node->parent_id;

            $this->actingAs(superAdminActor())
                ->putJson("/api/admin/system/menus/{$node->id}", menuPayloadOf($node, ['parent_id' => $disabled->id]))
                ->assertUnprocessable()
                ->assertJsonPath('errors.parent_id.0', 'The menu management nodes cannot be moved under a disabled node.');

            expect($node->fresh()?->parent_id)->toBe($parentId);
        })->with(['the menu node' => 'system.menu', 'its ancestor' => 'system']);
    });

    describe('making nodes take effect', function (): void {
        it('returns 422 when an operator who is not a super admin enables a disabled node granted to them', function (): void {
            $actor = userWithPermissions('system.menu.update', 'system.user.delete');
            $node = Permission::query()->where('name', 'system.user.delete')->sole();
            $node->update(['status' => Status::Disabled]);

            $this->actingAs($actor->fresh())
                ->putJson("/api/admin/system/menus/{$node->id}", menuPayloadOf($node, ['status' => 'enabled']))
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors(['status'])
                ->assertJsonPath('errors.status.0', 'Only super admins can make disabled menu nodes take effect again.');

            app()->forgetScopedInstances();
            expect($node->fresh()?->status)->toBe(Status::Disabled)
                ->and($actor->fresh()?->can('system.user.delete'))->toBeFalse();
        });

        it('returns 422 when an operator who is not a super admin enables an ancestor, which makes its descendants take effect', function (): void {
            $system = Permission::query()->where('name', 'system.user')->sole();
            $system->update(['status' => Status::Disabled]);

            $this->actingAs(userWithPermissions('system.menu.update'))
                ->putJson("/api/admin/system/menus/{$system->id}", menuPayloadOf($system, ['status' => 'enabled']))
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors(['status']);
        });

        it('returns 422 when an operator who is not a super admin moves a node out of a disabled ancestor', function (): void {
            $disabled = Permission::factory()->disabled()->create();
            $child = Permission::factory()->childOf($disabled)->create();

            $this->actingAs(userWithPermissions('system.menu.update'))
                ->putJson("/api/admin/system/menus/{$child->id}", menuPayloadOf($child, ['parent_id' => null]))
                ->assertUnprocessable()
                ->assertOnlyJsonValidationErrors(['parent_id']);

            expect($child->fresh()?->parent_id)->toBe($disabled->id);
        });

        it('lets operators who are not super admins make changes that keep nodes ineffective', function (): void {
            $disabled = Permission::factory()->disabled()->create();
            $child = Permission::factory()->disabled()->childOf($disabled)->create();
            $actor = userWithPermissions('system.menu.update');

            // 启用一个祖先仍停用的节点、移动停用的节点、停用节点、改标题：没有节点由不生效变为生效
            $this->actingAs($actor)->putJson("/api/admin/system/menus/{$child->id}", menuPayloadOf($child, ['status' => 'enabled']))->assertOk();
            $this->actingAs($actor)->putJson("/api/admin/system/menus/{$disabled->id}", menuPayloadOf($disabled, ['meta' => ['title' => 'Renamed']]))->assertOk();
            $this->actingAs($actor)->putJson("/api/admin/system/menus/{$this->catalog->id}", menuPayloadOf($this->catalog, ['status' => 'disabled']))->assertOk();
            $this->actingAs($actor)->putJson("/api/admin/system/menus/{$this->catalog->id}", menuPayloadOf($this->catalog, ['parent_id' => null, 'meta' => ['order' => 5]]))->assertOk();
        });

        it('lets super admins enable nodes and move them out of disabled ancestors', function (): void {
            $node = Permission::query()->where('name', 'system.user.delete')->sole();
            $node->update(['status' => Status::Disabled]);
            $disabled = Permission::factory()->disabled()->create();
            $child = Permission::factory()->childOf($disabled)->create();

            $this->actingAs(superAdminActor())->putJson("/api/admin/system/menus/{$node->id}", menuPayloadOf($node, ['status' => 'enabled']))->assertOk();
            $this->actingAs(superAdminActor())->putJson("/api/admin/system/menus/{$child->id}", menuPayloadOf($child, ['parent_id' => null]))->assertOk();
        });
    });

    it('changes /menu/all and /auth/codes right away', function (): void {
        $member = userWithPermissions('system.user', 'system.role');
        $user = Permission::query()->where('name', 'system.user')->sole();
        $role = Permission::query()->where('name', 'system.role')->sole();

        $this->actingAs($member)->getJson('/api/admin/auth/codes')->assertJsonPath('data', ['system', 'system.user', 'system.role']);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$user->id}", menuPayloadOf($user, ['status' => 'disabled']))
            ->assertOk();
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/menus/{$role->id}", menuPayloadOf($role, ['parent_id' => null, 'meta' => ['order' => 1]]))
            ->assertOk();

        $this->actingAs($member->fresh())->getJson('/api/admin/auth/codes')->assertJsonPath('data', ['system', 'system.role']);
        $this->actingAs($member->fresh())->getJson('/api/admin/menu/all')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'system.role')
            ->assertJsonPath('data.0.meta.order', 1);
    });
});

describe('destroy', function (): void {
    it('returns 401 for guests', function (): void {
        $node = Permission::factory()->create();

        $this->deleteJson("/api/admin/system/menus/{$node->id}")->assertUnauthorized();
    });

    it('returns 403 without the system.menu.delete code', function (): void {
        $node = Permission::factory()->create();

        $this->actingAs(userWithPermissions('system.menu', 'system.menu.update'))
            ->deleteJson("/api/admin/system/menus/{$node->id}")
            ->assertForbidden();

        $this->assertModelExists($node);
    });

    it('deletes a custom node and its grants, which changes the codes right away', function (): void {
        $node = Permission::factory()->childOf(Permission::query()->where('name', 'system.user')->sole())->button()->create();
        $role = Role::factory()->create();
        $role->givePermissionTo('system', 'system.user', $node->name);
        $member = User::factory()->create()->assignRole($role);

        $this->actingAs($member)->getJson('/api/admin/auth/codes')->assertJsonPath('data', ['system', 'system.user', $node->name]);

        $this->actingAs(userWithPermissions('system.menu.delete'))
            ->deleteJson("/api/admin/system/menus/{$node->id}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        $this->assertModelMissing($node);
        $this->assertDatabaseMissing('role_has_permissions', ['permission_id' => $node->id]);
        $this->actingAs($member->fresh())->getJson('/api/admin/auth/codes')->assertJsonPath('data', ['system', 'system.user']);
    });

    it('returns 403 for built-in nodes, even to super admins', function (): void {
        $node = Permission::query()->where('name', 'system.user.create')->sole();

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/system/menus/{$node->id}", [], ['Accept-Language' => 'zh-CN'])
            ->assertForbidden()
            ->assertExactJson(['code' => 403, 'data' => null, 'message' => '内置节点不能删除。']);

        $this->assertModelExists($node);
    });

    it('returns 422 for a node with children', function (): void {
        $catalog = Permission::factory()->create();
        Permission::factory()->childOf($catalog)->create();

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/system/menus/{$catalog->id}")
            ->assertUnprocessable()
            ->assertExactJson(['code' => 422, 'data' => null, 'message' => 'The node has child nodes and cannot be deleted.']);

        $this->assertModelExists($catalog);
    });
});
