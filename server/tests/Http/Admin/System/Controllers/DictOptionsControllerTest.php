<?php

use App\Core\System\Models\{DictItem, DictType, User};

/*
|--------------------------------------------------------------------------
| GET /options/dicts：表单下拉的字典选项（设计文档 §6.7）
|--------------------------------------------------------------------------
|
| 登录即可访问（不需要权限码）；只含启用类型下的启用项，按 sort、id 排序。
|
*/

beforeEach(function (): void {
    $gender = DictType::factory()->create(['code' => 'user_gender']);
    DictItem::factory()->for($gender, 'type')->create(['value' => 'female', 'label' => 'dict.female', 'color' => 'magenta', 'sort' => 2]);
    DictItem::factory()->for($gender, 'type')->create(['value' => 'male', 'label' => 'dict.male', 'sort' => 1]);
    DictItem::factory()->for($gender, 'type')->disabled()->create(['value' => 'unknown', 'sort' => 0]);

    $level = DictType::factory()->disabled()->create(['code' => 'customer_level']);
    DictItem::factory()->for($level, 'type')->create(['value' => 'vip']);
});

it('returns 401 for guests', function (): void {
    $this->getJson('/api/admin/options/dicts?types=user_gender')->assertUnauthorized();
});

it('is available to any signed-in user without a permission code', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin/options/dicts?types=user_gender')
        ->assertOk()
        ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['user_gender' => [
            ['value' => 'male', 'label' => 'dict.male', 'color' => null],
            ['value' => 'female', 'label' => 'dict.female', 'color' => 'magenta'],
        ]]]);
});

it('returns several types in the requested order, with [] for unknown and disabled types', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin/options/dicts?types=missing,customer_level,user_gender,missing')
        ->assertOk()
        ->assertJsonPath('data.missing', [])
        ->assertJsonPath('data.customer_level', [])
        ->assertJsonPath('data.user_gender.*.value', ['male', 'female'])
        ->assertJson(fn ($json) => $json->has('data', 3)->etc());
});

it('validates the types', function (string $query): void {
    $this->actingAs(User::factory()->create())
        ->getJson("/api/admin/options/dicts{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['types']);
})->with([
    'missing' => [''],
    'empty' => ['?types='],
    'invalid code' => ['?types=user_gender,Bad-Code'],
    'too many' => ['?types='.implode(',', array_map(fn (int $i): string => "type_{$i}", range(1, 51)))],
]);
