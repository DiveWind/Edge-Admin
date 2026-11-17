<?php

use App\Models\{Department, User};

beforeEach(function () {
    actingAsUser(superAdmin());
});

test('departments are returned as a tree with leaders', function () {
    $leader = User::factory()->create();
    $root = Department::factory()->create(['leader_id' => $leader->id, 'sort' => 0]);
    $child = Department::factory()->childOf($root)->create();
    Department::factory()->childOf($child)->create();

    $this->getJson('/api/departments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.leader', ['id' => $leader->id, 'name' => $leader->name])
        ->assertJsonPath('data.0.children.0.id', $child->id)
        ->assertJsonCount(1, 'data.0.children.0.children');
});

test('filtered departments keep matching nodes as roots', function () {
    $root = Department::factory()->create(['name' => '总公司']);
    Department::factory()->childOf($root)->create(['name' => '研发部']);

    $this->getJson('/api/departments?name=研发')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', '研发部');
});

test('departments can be created and updated', function () {
    $parent = Department::factory()->create();

    $id = $this->postJson('/api/departments', ['parent_id' => $parent->id, 'name' => '新部门', 'status' => 'active'])
        ->assertCreated()
        ->json('data.id');

    $this->putJson("/api/departments/{$id}", ['parent_id' => null, 'name' => '改名', 'status' => 'disabled'])
        ->assertOk()
        ->assertJsonPath('data.name', '改名')
        ->assertJsonPath('data.parent_id', null);
});

test('a department cannot become a child of itself or its descendants', function () {
    $parent = Department::factory()->create();
    $child = Department::factory()->childOf($parent)->create();

    $this->putJson("/api/departments/{$parent->id}", ['parent_id' => $parent->id, 'name' => 'x', 'status' => 'active'])
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    $this->putJson("/api/departments/{$parent->id}", ['parent_id' => $child->id, 'name' => 'x', 'status' => 'active'])
        ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

test('departments with children or users cannot be deleted', function () {
    $parent = Department::factory()->create();
    $child = Department::factory()->childOf($parent)->create();
    $withUser = Department::factory()->create();
    User::factory()->create(['department_id' => $withUser->id]);

    $this->deleteJson("/api/departments/{$parent->id}")->assertForbidden();
    $this->deleteJson("/api/departments/{$withUser->id}")->assertForbidden();
    $this->deleteJson("/api/departments/{$child->id}")->assertNoContent();
});
