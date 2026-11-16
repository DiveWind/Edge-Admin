<?php

use App\Models\User;

test('system info returns portable metrics without secrets', function () {
    actingAsUser(userWithPermissions(['monitor.system-info.view']));
    config(['edge.version' => '9.9.9']);

    $response = $this->getJson('/api/system-info')
        ->assertOk()
        ->assertJsonPath('data.app.version', '9.9.9')
        ->assertJsonPath('data.drivers.database.driver', 'sqlite')
        ->assertJsonStructure(['data' => [
            'app' => ['name', 'env', 'debug', 'version', 'locale', 'timezone'],
            'runtime' => ['php_version', 'laravel_version', 'os', 'sapi', 'memory_limit', 'upload_max_filesize', 'post_max_size', 'server_time'],
            'drivers' => ['database' => ['connection', 'driver', 'version'], 'cache', 'queue', 'session', 'filesystem', 'upload_disk'],
            'storage' => ['disk_total_bytes', 'disk_free_bytes', 'database_bytes', 'attachments_bytes'],
            'metrics' => ['users', 'online_sessions', 'attachments', 'failed_jobs'],
        ]]);

    expect($response->getContent())->not->toContain((string) config('app.key'));
});

test('system info requires its permission', function () {
    actingAsUser(User::factory()->create());

    $this->getJson('/api/system-info')->assertForbidden();
});

test('the root url exposes the configured version', function () {
    config(['edge.version' => '9.9.9']);

    $this->getJson('/')->assertOk()->assertJsonPath('version', '9.9.9');
});
