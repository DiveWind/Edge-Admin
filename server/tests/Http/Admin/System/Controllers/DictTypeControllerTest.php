<?php

use App\Core\System\Models\{DictItem, DictType};

/*
|--------------------------------------------------------------------------
| 字典类型管理：/system/dict-types 的列表、详情、新增、修改、删除（设计文档 §6.7、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/system/dict-types')->assertUnauthorized();
    });

    it('returns 403 without the system.dict code', function (): void {
        $this->actingAs(userWithPermissions('system.setting'))
            ->getJson('/api/admin/system/dict-types')
            ->assertForbidden();
    });

    it('paginates the types with their fields, ordered by id', function (): void {
        $first = DictType::factory()->builtin()->create(['code' => 'user_gender', 'name' => 'dict.gender', 'remark' => 'Gender']);
        DictType::factory()->create(['code' => 'customer_level']);

        $this->actingAs(userWithPermissions('system.dict'))
            ->getJson('/api/admin/system/dict-types?pageSize=1')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['total' => 2, 'items' => [[
                'id' => $first->id,
                'code' => 'user_gender',
                'name' => 'dict.gender',
                'status' => 'enabled',
                'remark' => 'Gender',
                'is_builtin' => true,
                'created_at' => $first->created_at?->toIso8601String(),
                'updated_at' => $first->updated_at?->toIso8601String(),
            ]]]]);
    });

    it('filters by code, name and status', function (string $query, array $codes): void {
        DictType::factory()->create(['code' => 'user_gender', 'name' => 'Gender']);
        DictType::factory()->disabled()->create(['code' => 'customer_level', 'name' => 'Customer level']);

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/dict-types?{$query}")
            ->assertOk()
            ->assertJsonPath('data.items.*.code', $codes);
    })->with([
        'code contains' => ['filter[code]=gender', ['user_gender']],
        'name contains' => ['filter[name]=level', ['customer_level']],
        'status equals' => ['filter[status]=disabled', ['customer_level']],
    ]);

    it('returns 422 for filters and sorts outside the allowlist', function (string $query, string $field): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/dict-types?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'filter' => ['filter[remark]=x', 'filter'],
        'sort' => ['sort=name', 'sort'],
        'status value' => ['filter[status]=active', 'filter.status'],
    ]);
});

describe('show', function (): void {
    it('returns 403 without the system.dict code', function (): void {
        $type = DictType::factory()->create();

        $this->actingAs(userWithPermissions('system.setting'))
            ->getJson("/api/admin/system/dict-types/{$type->id}")
            ->assertForbidden();
    });

    it('returns the type', function (): void {
        $type = DictType::factory()->create();

        $this->actingAs(userWithPermissions('system.dict'))
            ->getJson("/api/admin/system/dict-types/{$type->id}")
            ->assertOk()
            ->assertJsonPath('data.code', $type->code);
    });

    it('returns 404 for unknown and malformed ids', function (string $id): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/dict-types/{$id}")
            ->assertNotFound();
    })->with(['missing' => '999999', 'not a number' => 'abc']);
});

describe('store', function (): void {
    it('returns 401 for guests', function (): void {
        $this->postJson('/api/admin/system/dict-types', ['code' => 'user_gender', 'name' => 'Gender'])->assertUnauthorized();
    });

    it('returns 403 without the system.dict.create code', function (): void {
        $this->actingAs(userWithPermissions('system.dict', 'system.dict.update'))
            ->postJson('/api/admin/system/dict-types', ['code' => 'user_gender', 'name' => 'Gender'])
            ->assertForbidden();

        $this->assertDatabaseCount('dict_types', 0);
    });

    it('creates a custom type with defaults', function (): void {
        $this->actingAs(userWithPermissions('system.dict.create'))
            ->postJson('/api/admin/system/dict-types', ['code' => 'content.article_source', 'name' => 'Article source'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'content.article_source')
            ->assertJsonPath('data.status', 'enabled')
            ->assertJsonPath('data.remark', null)
            ->assertJsonPath('data.is_builtin', false);
    });

    it('validates the fields', function (array $payload, string $field): void {
        DictType::factory()->create(['code' => 'user_gender']);

        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/system/dict-types', [...['code' => 'customer_level', 'name' => 'Level'], ...$payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'missing code' => [['code' => null], 'code'],
        'code in upper case' => [['code' => 'User_Gender'], 'code'],
        'code starting with a digit' => [['code' => '1gender'], 'code'],
        'duplicate code' => [['code' => 'user_gender'], 'code'],
        'missing name' => [['name' => null], 'name'],
        'unknown status' => [['status' => 'active'], 'status'],
        'unknown field' => [['is_builtin' => true], 'is_builtin'],
    ]);
});

describe('update', function (): void {
    it('returns 403 without the system.dict.update code', function (): void {
        $type = DictType::factory()->create();

        $this->actingAs(userWithPermissions('system.dict', 'system.dict.create'))
            ->putJson("/api/admin/system/dict-types/{$type->id}", ['code' => 'other', 'name' => 'Other'])
            ->assertForbidden();
    });

    it('updates every field of a custom type', function (): void {
        $type = DictType::factory()->create(['code' => 'old_code']);

        $this->actingAs(userWithPermissions('system.dict.update'))
            ->putJson("/api/admin/system/dict-types/{$type->id}", ['code' => 'new_code', 'name' => 'New', 'status' => 'disabled', 'remark' => 'Note'])
            ->assertOk()
            ->assertJsonPath('data.code', 'new_code')
            ->assertJsonPath('data.status', 'disabled')
            ->assertJsonPath('data.remark', 'Note');
    });

    it('lets administrators adjust a built-in type but not its code', function (): void {
        $type = DictType::factory()->builtin()->create(['code' => 'user_gender']);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/dict-types/{$type->id}", ['code' => 'user_gender', 'name' => 'Renamed', 'status' => 'disabled'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/dict-types/{$type->id}", ['code' => 'gender', 'name' => 'Renamed'], ['Accept-Language' => 'zh-CN'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', '内置字典类型的编码不能修改。');

        expect($type->fresh()->code)->toBe('user_gender');
    });

    it('returns 422 for a code taken by another type', function (): void {
        DictType::factory()->create(['code' => 'taken']);
        $type = DictType::factory()->create();

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/dict-types/{$type->id}", ['code' => 'taken', 'name' => 'Name'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });
});

describe('destroy', function (): void {
    it('returns 403 without the system.dict.delete code', function (): void {
        $type = DictType::factory()->create();

        $this->actingAs(userWithPermissions('system.dict', 'system.dict.update'))
            ->deleteJson("/api/admin/system/dict-types/{$type->id}")
            ->assertForbidden();

        $this->assertModelExists($type);
    });

    it('deletes a custom type together with its items', function (): void {
        $type = DictType::factory()->create();
        DictItem::factory()->count(2)->for($type, 'type')->create();
        $other = DictItem::factory()->create();

        $this->actingAs(userWithPermissions('system.dict.delete'))
            ->deleteJson("/api/admin/system/dict-types/{$type->id}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        $this->assertModelMissing($type);
        expect(DictItem::query()->pluck('id')->all())->toBe([$other->id]);
    });

    it('returns 403 for a built-in type, also to super admins', function (): void {
        $type = DictType::factory()->builtin()->create();

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/system/dict-types/{$type->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Built-in dictionary types cannot be deleted.');

        $this->assertModelExists($type);
    });
});
