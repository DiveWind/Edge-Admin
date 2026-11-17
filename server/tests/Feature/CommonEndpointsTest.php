<?php

use App\Models\{ArticleCategory, Department, LoginLog, Position, Role, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the root url returns the application name and version', function () {
    $this->getJson('/')->assertOk()->assertJsonStructure(['name', 'version']);
});

test('options only return active items and only require login', function () {
    actingAsUser(User::factory()->create());
    $root = Department::factory()->create();
    Department::factory()->childOf($root)->create();
    Department::factory()->create(['status' => 'disabled']);
    Role::factory()->create();
    Role::factory()->disabled()->create();
    Position::factory()->create();
    ArticleCategory::factory()->create();

    $this->getJson('/api/options/departments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'parent_id', 'children' => [['id', 'name', 'parent_id', 'children']]]]]);
    $this->getJson('/api/options/roles')->assertOk()->assertJsonCount(1, 'data')->assertJsonStructure(['data' => [['id', 'name', 'code']]]);
    $this->getJson('/api/options/positions')->assertOk()->assertJsonStructure(['data' => [['id', 'name', 'code']]]);
    $this->getJson('/api/options/article-categories')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/options/users')->assertOk()->assertJsonStructure(['data' => [['id', 'name']]]);
});

test('permission tree option only requires login', function () {
    $this->artisan('permission:sync');
    actingAsUser(User::factory()->create());

    $this->getJson('/api/options/permissions')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'dashboard')
        ->assertJsonStructure(['data' => [['id', 'parent_id', 'name', 'code', 'type', 'sort', 'children']]]);
    $this->getJson('/api/permissions')->assertForbidden();
});

test('images can be uploaded to the public disk', function () {
    Storage::fake('public');
    actingAsUser(User::factory()->create());

    $response = $this->postJson('/api/uploads', ['file' => UploadedFile::fake()->image('avatar.png')])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['url', 'path']]);

    Storage::disk('public')->assertExists($response->json('data.path'));

    $this->postJson('/api/uploads', ['file' => UploadedFile::fake()->create('script.php', 10, 'application/x-php')])->assertUnprocessable();
    $this->postJson('/api/uploads', ['file' => UploadedFile::fake()->create('vector.svg', 10, 'image/svg+xml')])->assertUnprocessable();
});

test('dashboard returns statistics', function () {
    actingAsUser(superAdmin());
    LoginLog::factory()->create(['created_at' => now()]);

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'totals' => ['users', 'roles', 'departments', 'articles', 'today_logins', 'today_operations', 'online_sessions', 'attachments'],
            'login_trend' => [['date', 'success', 'failed']],
            'articles_by_category',
            'users_by_department',
            'recent_operations',
        ]])
        ->assertJsonCount(14, 'data.login_trend')
        ->assertJsonPath('data.login_trend.13.date', today()->toDateString());
});

test('dashboard requires the dashboard permission', function () {
    actingAsUser(User::factory()->create());

    $this->getJson('/api/dashboard')->assertForbidden();
});
