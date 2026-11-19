<?php

use App\Core\System\Models\User;

/*
|--------------------------------------------------------------------------
| GET /options/enums：已登记的枚举的选项（设计文档 §6.7）
|--------------------------------------------------------------------------
|
| 显式白名单（EnumRegistry）：未登记的名称 422；label 按 Accept-Language 翻译；登录即可访问。
|
*/

it('returns 401 for guests', function (): void {
    $this->getJson('/api/admin/options/enums?names=status')->assertUnauthorized();
});

it('returns the options of the registered enums in the requested order', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin/options/enums?names=status,login_event')
        ->assertOk()
        ->assertJsonPath('data.status', [['value' => 'enabled', 'label' => 'Enabled', 'color' => 'success'], ['value' => 'disabled', 'label' => 'Disabled', 'color' => 'error']])
        ->assertJsonPath('data.login_event.*.value', ['login', 'logout', 'failed', 'lockout', 'password_reset']);
});

it('registers the Core enums', function (string $name): void {
    $this->actingAs(User::factory()->create())
        ->getJson("/api/admin/options/enums?names={$name}")
        ->assertOk()
        ->assertJsonPath("data.{$name}.0.value", fn (mixed $value): bool => is_string($value));
})->with(['status', 'permission_type', 'setting_type', 'login_event', 'login_failure_reason']);

it('translates the labels by the Accept-Language header', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin/options/enums?names=status', ['Accept-Language' => 'zh-CN'])
        ->assertOk()
        ->assertJsonPath('data.status.*.label', ['启用', '停用']);
});

it('returns 422 for names that are not registered, even if a matching enum class exists', function (string $names): void {
    $this->actingAs(User::factory()->create())
        ->getJson("/api/admin/options/enums?names={$names}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['names' => 'Unknown enum: '.explode(',', $names)[1].'.']);
})->with([
    'unknown name' => ['status,unknown'],
    'class name' => ['status,App\Core\System\Enums\Status'],
    'class basename' => ['status,Status'],
]);

it('requires the names', function (): void {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin/options/enums')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['names']);
});
