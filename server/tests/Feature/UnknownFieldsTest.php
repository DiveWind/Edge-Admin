<?php

use App\Models\{Position, Setting};

/*
|--------------------------------------------------------------------------
| 未声明字段（FormRequest::failOnUnknownFields()，见 AppServiceProvider）
|--------------------------------------------------------------------------
|
| 写接口的请求体只能包含 rules() 中声明的字段（含 `*_confirmation`），否则 422，错误挂在该字段上。
| 查询参数不在检查范围内（列表过滤由各 IndexRequest 的规则约束）。
|
*/

beforeEach(fn () => actingAsUser(superAdmin()));

test('write requests reject fields that are not part of the contract', function () {
    $this->postJson('/api/positions', ['name' => '岗位', 'code' => 'p1', 'status' => 'active', 'id' => 99, 'nmae' => '拼错'])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['id', 'nmae']);

    expect(Position::query()->count())->toBe(0);
});

test('nested unknown keys are rejected while wildcard rules and confirmations are allowed', function () {
    $this->putJson('/api/auth/password', ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertNoContent();

    $this->postJson('/api/roles', ['name' => '角色', 'code' => 'r1', 'data_scope' => 'all', 'status' => 'active', 'permission_ids' => [], 'extra' => ['a' => 1]])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['extra.a']);
});

test('json settings accept arbitrary objects as the value', function () {
    $this->postJson('/api/settings', ['group' => 'demo', 'key' => 'demo.menu', 'name' => '菜单', 'type' => 'json', 'value' => ['items' => [['label' => '首页']]]])
        ->assertCreated()
        ->assertJsonPath('data.value.items.0.label', '首页');

    expect(Setting::query()->where('key', 'demo.menu')->firstOrFail()->value)->toBe(['items' => [['label' => '首页']]]);
});

test('query parameters of list requests are not subject to the unknown field check', function () {
    $this->getJson('/api/positions?name=x&_t=123')->assertOk();
});
