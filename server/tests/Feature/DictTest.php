<?php

use App\Models\{Article, ArticleCategory, DictItem, DictType, User};

test('dictionary types can be managed and filtered', function () {
    actingAsUser(userWithPermissions(['system.dict.view', 'system.dict.create', 'system.dict.update']));
    DictType::factory()->create(['code' => 'other_dict']);

    $id = $this->postJson('/api/dict-types', ['name' => '订单状态', 'code' => 'order_status', 'status' => 'active'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'order_status')
        ->json('data.id');

    $this->postJson('/api/dict-types', ['name' => '重复', 'code' => 'order_status', 'status' => 'active'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->putJson("/api/dict-types/{$id}", ['name' => '订单状态（新）', 'code' => 'order_status', 'status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled');

    $this->getJson('/api/dict-types?code=order')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.items_count', 0);
});

test('dictionary types with items cannot be deleted', function () {
    actingAsUser(superAdmin());
    $type = DictType::factory()->has(DictItem::factory(), 'items')->create();

    $this->deleteJson("/api/dict-types/{$type->id}")
        ->assertForbidden()
        ->assertJsonPath('message', '该字典下存在字典项，不能删除。');
    $this->deleteJson('/api/dict-types/'.DictType::factory()->create()->id)->assertNoContent();
});

test('dictionary item values are unique within a type and the list requires a type', function () {
    actingAsUser(superAdmin());
    $type = DictType::factory()->create();
    DictItem::factory()->for($type, 'type')->create(['value' => 'a', 'sort' => 0]);
    DictItem::factory()->create(['value' => 'b']);

    $payload = ['dict_type_id' => $type->id, 'label' => 'A', 'value' => 'a', 'status' => 'active'];
    $this->postJson('/api/dict-items', $payload)->assertUnprocessable()->assertJsonValidationErrors('value');
    $this->postJson('/api/dict-items', [...$payload, 'value' => 'b', 'color' => 'red', 'sort' => 2])
        ->assertCreated()
        ->assertJsonPath('data.color', 'red');

    $this->getJson('/api/dict-items')->assertUnprocessable()->assertJsonValidationErrors('dict_type_id');
    $this->getJson("/api/dict-items?dict_type_id={$type->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.value', 'a');
});

test('dictionary management requires the dict permissions', function () {
    actingAsUser(userWithPermissions(['system.dict.view']));
    $type = DictType::factory()->create();

    $this->getJson('/api/dict-types')->assertOk();
    $this->postJson('/api/dict-types', ['name' => 'x', 'code' => 'x', 'status' => 'active'])->assertForbidden();
    $this->postJson('/api/dict-items', ['dict_type_id' => $type->id, 'label' => 'x', 'value' => 'x', 'status' => 'active'])->assertForbidden();
    $this->deleteJson("/api/dict-types/{$type->id}")->assertForbidden();
});

test('dictionary options only return active items of active types in sort order', function () {
    actingAsUser(User::factory()->create());
    $type = DictType::factory()->create(['code' => 'level']);
    DictItem::factory()->for($type, 'type')->create(['label' => '高', 'value' => 'high', 'sort' => 2, 'color' => 'red']);
    DictItem::factory()->for($type, 'type')->create(['label' => '低', 'value' => 'low', 'sort' => 1]);
    DictItem::factory()->for($type, 'type')->disabled()->create(['value' => 'hidden']);
    DictItem::factory()->for(DictType::factory()->disabled()->create(['code' => 'off']), 'type')->create();

    $this->getJson('/api/options/dicts?codes=level,off,missing')
        ->assertOk()
        ->assertExactJson(['data' => [
            'level' => [
                ['label' => '低', 'value' => 'low', 'color' => null],
                ['label' => '高', 'value' => 'high', 'color' => 'red'],
            ],
            'off' => [],
            'missing' => [],
        ]]);

    $this->getJson('/api/options/dicts')->assertUnprocessable()->assertJsonValidationErrors('codes');
});

test('the dictionary cache is refreshed when items change', function () {
    actingAsUser(superAdmin());
    $type = DictType::factory()->create(['code' => 'level']);

    $this->getJson('/api/options/dicts?codes=level')->assertJsonPath('data.level', []);

    $this->postJson('/api/dict-items', ['dict_type_id' => $type->id, 'label' => '高', 'value' => 'high', 'status' => 'active'])->assertCreated();

    $this->getJson('/api/options/dicts?codes=level')->assertJsonPath('data.level.0.value', 'high');
});

test('article source is validated against the article_source dictionary', function () {
    actingAsUser(superAdmin());
    $type = DictType::factory()->create(['code' => 'article_source']);
    DictItem::factory()->for($type, 'type')->create(['value' => 'original']);
    DictItem::factory()->for($type, 'type')->create(['value' => 'reprint']);
    DictItem::factory()->for($type, 'type')->disabled()->create(['value' => 'translation']);
    $payload = ['category_id' => ArticleCategory::factory()->create()->id, 'title' => '标题', 'content' => '正文', 'status' => 'draft'];

    $this->postJson('/api/articles', $payload)->assertCreated()->assertJsonPath('data.source', 'original');
    $this->postJson('/api/articles', [...$payload, 'source' => 'reprint'])->assertCreated()->assertJsonPath('data.source', 'reprint');
    $this->postJson('/api/articles', [...$payload, 'source' => 'translation'])->assertUnprocessable()->assertJsonValidationErrors('source');

    expect(Article::query()->count())->toBe(2);
    $this->getJson('/api/articles?source=reprint')->assertOk()->assertJsonCount(1, 'data');
});
