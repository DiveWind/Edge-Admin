<?php

use App\Enums\DataScope;
use App\Models\Article;
use App\Models\Department;
use App\Models\User;

/**
 * 部门树：root → a → a1，root → b；当前用户属于 a。
 */
beforeEach(function () {
    $this->root = Department::factory()->create(['name' => 'root']);
    $this->a = Department::factory()->childOf($this->root)->create(['name' => 'a']);
    $this->a1 = Department::factory()->childOf($this->a)->create(['name' => 'a1']);
    $this->b = Department::factory()->childOf($this->root)->create(['name' => 'b']);

    $this->articles = collect(['root', 'a', 'a1', 'b'])->mapWithKeys(fn (string $name) => [
        $name => Article::factory()->by(User::factory()->create(['department_id' => $this->{$name}->id]))->create(['title' => $name]),
    ]);

    $this->makeActor = function (DataScope $scope): User {
        $actor = userWithPermissions(['content.article.view'], $scope, ['department_id' => $this->a->id]);
        $this->articles['own'] = Article::factory()->by($actor)->create(['title' => 'own']);

        return $actor;
    };
});

test('article list respects data scope', function (DataScope $scope, array $expected) {
    $actor = ($this->makeActor)($scope);

    if ($scope === DataScope::Custom) {
        $actor->roles->first()->departments()->sync([$this->b->id]);
    }

    actingAsUser($actor->fresh());

    $titles = collect($this->getJson('/api/articles?per_page=100')->assertOk()->json('data'))->pluck('title')->sort()->values()->all();

    expect($titles)->toBe(collect($expected)->sort()->values()->all());
})->with([
    'all' => [DataScope::All, ['root', 'a', 'a1', 'b', 'own']],
    'custom' => [DataScope::Custom, ['b']],
    'department and children' => [DataScope::DepartmentAndChildren, ['a', 'a1', 'own']],
    'department' => [DataScope::Department, ['a', 'own']],
    'self' => [DataScope::Self, ['own']],
]);

test('single article access respects data scope', function (DataScope $scope, string $title, int $status) {
    $actor = ($this->makeActor)($scope);

    if ($scope === DataScope::Custom) {
        $actor->roles->first()->departments()->sync([$this->b->id]);
    }

    actingAsUser($actor->fresh());

    $this->getJson("/api/articles/{$this->articles[$title]->id}")->assertStatus($status);
})->with([
    [DataScope::All, 'b', 200],
    [DataScope::Custom, 'b', 200],
    [DataScope::Custom, 'a', 403],
    [DataScope::DepartmentAndChildren, 'a1', 200],
    [DataScope::DepartmentAndChildren, 'b', 403],
    [DataScope::Department, 'a', 200],
    [DataScope::Department, 'a1', 403],
    [DataScope::Self, 'own', 200],
    [DataScope::Self, 'a', 403],
]);

test('multiple roles are combined as a union', function () {
    $actor = ($this->makeActor)(DataScope::Self);
    $other = userWithPermissions([], DataScope::Custom);
    $customRole = $other->roles->first();
    $customRole->departments()->sync([$this->b->id]);
    $actor->roles()->attach($customRole);

    actingAsUser($actor->fresh());

    $titles = collect($this->getJson('/api/articles')->json('data'))->pluck('title')->sort()->values()->all();

    expect($titles)->toBe(['b', 'own']);
});

test('super admin always sees everything', function () {
    actingAsUser(superAdmin(['department_id' => $this->a1->id]));

    $this->getJson('/api/articles')->assertOk()->assertJsonCount(4, 'data');
});

test('updating and deleting articles outside the data scope is forbidden', function () {
    $actor = userWithPermissions(['content.article.update', 'content.article.delete'], DataScope::Department, ['department_id' => $this->a->id]);
    actingAsUser($actor);

    $article = $this->articles['b'];

    $this->putJson("/api/articles/{$article->id}", [
        'category_id' => $article->category_id,
        'title' => 'x',
        'content' => 'x',
        'status' => 'draft',
    ])->assertForbidden();
    $this->deleteJson("/api/articles/{$article->id}")->assertForbidden();
    $this->deleteJson("/api/articles/{$this->articles['a']->id}")->assertNoContent();
});

test('user list respects data scope', function () {
    $actor = userWithPermissions(['system.user.view'], DataScope::Department, ['department_id' => $this->b->id]);
    actingAsUser($actor);

    $ids = collect($this->getJson('/api/users?per_page=100')->json('data'))->pluck('department_id')->unique()->values()->all();

    expect($ids)->toBe([$this->b->id]);
});
