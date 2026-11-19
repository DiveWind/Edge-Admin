<?php

use App\Core\System\Models\{DictItem, DictType};

/*
|--------------------------------------------------------------------------
| 字典项管理：/system/dict-types/{dict_type}/items 与 /system/dict-items/{dict_item}（设计文档 §6.7、§8.6）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->type = DictType::factory()->create(['code' => 'user_gender']);
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson("/api/admin/system/dict-types/{$this->type->id}/items")->assertUnauthorized();
    });

    it('returns 403 without the system.dict code', function (): void {
        $this->actingAs(userWithPermissions('system.setting'))
            ->getJson("/api/admin/system/dict-types/{$this->type->id}/items")
            ->assertForbidden();
    });

    it('lists the items of the type, disabled ones included, ordered by sort and id', function (): void {
        $female = DictItem::factory()->for($this->type, 'type')->create(['value' => 'female', 'sort' => 2]);
        $male = DictItem::factory()->for($this->type, 'type')->disabled()->create(['value' => 'male', 'label' => 'dict.male', 'color' => 'blue', 'sort' => 1]);
        DictItem::factory()->create(['value' => 'other_type']);

        $this->actingAs(userWithPermissions('system.dict'))
            ->getJson("/api/admin/system/dict-types/{$this->type->id}/items")
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.*.id', [$male->id, $female->id])
            ->assertJsonPath('data.items.0', [
                'id' => $male->id,
                'dict_type_id' => $this->type->id,
                'value' => 'male',
                'label' => 'dict.male',
                'color' => 'blue',
                'sort' => 1,
                'status' => 'disabled',
                'is_builtin' => false,
                'created_at' => $male->created_at?->toIso8601String(),
                'updated_at' => $male->updated_at?->toIso8601String(),
            ]);
    });

    it('filters by value, label and status', function (string $query, array $values): void {
        DictItem::factory()->for($this->type, 'type')->create(['value' => 'male', 'label' => 'Man']);
        DictItem::factory()->for($this->type, 'type')->disabled()->create(['value' => 'female', 'label' => 'Woman']);

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/dict-types/{$this->type->id}/items?{$query}")
            ->assertOk()
            ->assertJsonPath('data.items.*.value', $values);
    })->with([
        'value contains' => ['filter[value]=fem', ['female']],
        'label contains' => ['filter[label]=man', ['male', 'female']],
        'status equals' => ['filter[status]=enabled', ['male']],
    ]);

    it('returns 422 for filters outside the allowlist', function (): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/system/dict-types/{$this->type->id}/items?filter[color]=blue")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['filter']);
    });

    it('returns 404 for an unknown type', function (): void {
        $this->actingAs(superAdminActor())
            ->getJson('/api/admin/system/dict-types/999999/items')
            ->assertNotFound();
    });
});

describe('store', function (): void {
    it('returns 401 for guests', function (): void {
        $this->postJson("/api/admin/system/dict-types/{$this->type->id}/items", ['value' => 'male', 'label' => 'Male'])->assertUnauthorized();
    });

    it('returns 403 without the system.dict.create code', function (): void {
        $this->actingAs(userWithPermissions('system.dict', 'system.dict.update'))
            ->postJson("/api/admin/system/dict-types/{$this->type->id}/items", ['value' => 'male', 'label' => 'Male'])
            ->assertForbidden();

        $this->assertDatabaseCount('dict_items', 0);
    });

    it('creates a custom item in the type with defaults', function (): void {
        $this->actingAs(userWithPermissions('system.dict.create'))
            ->postJson("/api/admin/system/dict-types/{$this->type->id}/items", ['value' => 'male', 'label' => 'Male', 'color' => '#1677ff'])
            ->assertCreated()
            ->assertJsonPath('data.dict_type_id', $this->type->id)
            ->assertJsonPath('data.value', 'male')
            ->assertJsonPath('data.color', '#1677ff')
            ->assertJsonPath('data.sort', 0)
            ->assertJsonPath('data.status', 'enabled')
            ->assertJsonPath('data.is_builtin', false);
    });

    it('allows the same value in another type', function (): void {
        DictItem::factory()->create(['value' => 'male']);

        $this->actingAs(superAdminActor())
            ->postJson("/api/admin/system/dict-types/{$this->type->id}/items", ['value' => 'male', 'label' => 'Male'])
            ->assertCreated();
    });

    it('validates the fields', function (array $payload, string $field): void {
        DictItem::factory()->for($this->type, 'type')->create(['value' => 'taken']);

        $this->actingAs(superAdminActor())
            ->postJson("/api/admin/system/dict-types/{$this->type->id}/items", [...['value' => 'male', 'label' => 'Male'], ...$payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'missing value' => [['value' => null], 'value'],
        'value too long' => [['value' => str_repeat('a', 65)], 'value'],
        'duplicate value in the type' => [['value' => 'taken'], 'value'],
        'missing label' => [['label' => null], 'label'],
        'invalid color' => [['color' => 'red;background:url(x)'], 'color'],
        'negative sort' => [['sort' => -1], 'sort'],
        'unknown status' => [['status' => 'active'], 'status'],
        'type in the body' => [['dict_type_id' => 1], 'dict_type_id'],
    ]);
});

describe('update', function (): void {
    it('returns 403 without the system.dict.update code', function (): void {
        $item = DictItem::factory()->for($this->type, 'type')->create();

        $this->actingAs(userWithPermissions('system.dict', 'system.dict.create'))
            ->putJson("/api/admin/system/dict-items/{$item->id}", ['value' => 'x', 'label' => 'X'])
            ->assertForbidden();
    });

    it('updates every field of a custom item', function (): void {
        $item = DictItem::factory()->for($this->type, 'type')->create(['value' => 'old']);

        $this->actingAs(userWithPermissions('system.dict.update'))
            ->putJson("/api/admin/system/dict-items/{$item->id}", ['value' => 'new', 'label' => 'New', 'color' => null, 'sort' => 3, 'status' => 'disabled'])
            ->assertOk()
            ->assertJsonPath('data.value', 'new')
            ->assertJsonPath('data.sort', 3)
            ->assertJsonPath('data.status', 'disabled');
    });

    it('lets administrators adjust a built-in item but not its value', function (): void {
        $item = DictItem::factory()->for($this->type, 'type')->builtin()->create(['value' => 'male']);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/dict-items/{$item->id}", ['value' => 'male', 'label' => 'Renamed', 'color' => 'success', 'sort' => 9, 'status' => 'disabled'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Renamed');

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/dict-items/{$item->id}", ['value' => 'man', 'label' => 'Renamed'], ['Accept-Language' => 'zh-CN'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.value.0', '内置字典项的值不能修改。');

        expect($item->fresh()->value)->toBe('male');
    });

    it('returns 422 for a value taken by another item of the type', function (): void {
        DictItem::factory()->for($this->type, 'type')->create(['value' => 'taken']);
        $item = DictItem::factory()->for($this->type, 'type')->create();

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/dict-items/{$item->id}", ['value' => 'taken', 'label' => 'Label'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    });
});

describe('destroy', function (): void {
    it('returns 403 without the system.dict.delete code', function (): void {
        $item = DictItem::factory()->for($this->type, 'type')->create();

        $this->actingAs(userWithPermissions('system.dict', 'system.dict.update'))
            ->deleteJson("/api/admin/system/dict-items/{$item->id}")
            ->assertForbidden();
    });

    it('deletes a custom item', function (): void {
        $item = DictItem::factory()->for($this->type, 'type')->create();

        $this->actingAs(userWithPermissions('system.dict.delete'))
            ->deleteJson("/api/admin/system/dict-items/{$item->id}")
            ->assertOk();

        $this->assertModelMissing($item);
    });

    it('returns 403 for a built-in item, also to super admins', function (): void {
        $item = DictItem::factory()->for($this->type, 'type')->builtin()->create();

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/system/dict-items/{$item->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Built-in dictionary items cannot be deleted.');

        $this->assertModelExists($item);
    });
});
