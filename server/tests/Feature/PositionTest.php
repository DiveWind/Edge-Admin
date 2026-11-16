<?php

use App\Models\Position;
use App\Models\User;

beforeEach(function () {
    actingAsUser(superAdmin());
});

test('positions support crud', function () {
    $id = $this->postJson('/api/positions', ['name' => '工程师', 'code' => 'engineer', 'status' => 'active'])
        ->assertCreated()
        ->json('data.id');

    $this->getJson('/api/positions?name=工程')->assertOk()->assertJsonCount(1, 'data');
    $this->putJson("/api/positions/{$id}", ['name' => '高级工程师', 'code' => 'engineer', 'status' => 'active'])
        ->assertOk()->assertJsonPath('data.name', '高级工程师');
    $this->postJson('/api/positions', ['name' => 'x', 'code' => 'engineer', 'status' => 'active'])
        ->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->deleteJson("/api/positions/{$id}")->assertNoContent();
});

test('positions with users cannot be deleted', function () {
    $position = Position::factory()->create();
    User::factory()->create()->positions()->attach($position);

    $this->deleteJson("/api/positions/{$position->id}")->assertForbidden();
});
