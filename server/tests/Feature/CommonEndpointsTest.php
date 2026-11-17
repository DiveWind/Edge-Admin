<?php

use App\Models\{ArticleCategory, Department, LoginLog, Position, Role, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};

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

test('dashboard aggregates are served stale-while-revalidate', function () {
    config(['edge.statistics_cache' => ['fresh' => 60, 'stale' => 300]]);
    actingAsUser(superAdmin());
    $this->freezeTime();

    $users = $this->getJson('/api/dashboard')->assertOk()->json('data.totals.users');
    User::factory()->create();

    // 新鲜期内返回缓存
    $this->getJson('/api/dashboard')->assertJsonPath('data.totals.users', $users);

    // 过了新鲜期：本次仍返回旧值，响应发送后（deferred）重新计算，下一次拿到新值
    $this->travel(61)->seconds();
    $this->getJson('/api/dashboard')->assertJsonPath('data.totals.users', $users);
    $this->getJson('/api/dashboard')->assertJsonPath('data.totals.users', $users + 1);
});

test('dashboard caching can be disabled', function () {
    config(['edge.statistics_cache' => ['fresh' => 0, 'stale' => 0]]);
    actingAsUser(superAdmin());

    $users = $this->getJson('/api/dashboard')->json('data.totals.users');
    User::factory()->create();

    $this->getJson('/api/dashboard')->assertJsonPath('data.totals.users', $users + 1);
});

test('the health route checks the database connection', function () {
    $this->get('/up')->assertOk();

    DB::shouldReceive('connection->getPdo')->andThrow(new RuntimeException('database is down'));

    $this->get('/up')->assertServerError();
});

test('php artisan about shows the Edge Admin section', function () {
    $this->artisan('about', ['--only' => 'edge_admin', '--json' => true])
        ->expectsOutputToContain('"upload_max_size":"2 MB"')
        ->assertSuccessful();
});
