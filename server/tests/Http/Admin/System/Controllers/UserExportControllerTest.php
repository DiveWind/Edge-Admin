<?php

use App\Core\Message\Models\Notification;
use App\Core\System\Enums\{ExportFormat, ExportStatus};
use App\Core\System\Jobs\RunExport;
use App\Core\System\Models\{Export, User};
use Illuminate\Support\Facades\{Queue, Storage};
use Spatie\SimpleExcel\SimpleExcelReader;

/*
|--------------------------------------------------------------------------
| 导出用户列表：POST /system/users/export（设计文档 §6.10）
|--------------------------------------------------------------------------
|
| 测试的队列驱动为 sync：事务提交后 RunExport 在请求中执行，响应里的记录是入队前的状态（queued），库中已是执行后的状态。
| 生成文件的细节（格式、防公式注入、语言、失败）见 tests/Core/System/Actions/GenerateExportTest.php。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    Storage::fake('local');
});

/**
 * 导出文件中的数据行（不含表头）。
 *
 * @return list<list<mixed>>
 */
function exportedRows(Export $export): array
{
    $file = $export->file()->sole();

    return SimpleExcelReader::create(Storage::disk($file->disk)->path($file->path), $file->extension)->noHeaderRow()->getRows()->skip(1)->values()->all();
}

it('returns 401 for guests', function (): void {
    $this->postJson('/api/admin/system/users/export')->assertUnauthorized();

    expect(Export::query()->count())->toBe(0);
});

it('returns 403 without system.user.export, even with the list permission', function (): void {
    $this->actingAs(userWithPermissions('system.user'))
        ->postJson('/api/admin/system/users/export')
        ->assertForbidden();

    expect(Export::query()->count())->toBe(0);
});

it('creates a queued export and returns 201 at once', function (): void {
    Queue::fake();
    $actor = userWithPermissions('system.user.export');

    $response = $this->actingAs($actor)
        ->withHeader('Accept-Language', 'zh-CN')
        ->postJson('/api/admin/system/users/export', ['format' => 'csv', 'filter' => ['status' => 'enabled'], 'sort' => '-created_at'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'system.user')
        ->assertJsonPath('data.title', '用户')
        ->assertJsonPath('data.format', 'csv')
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.params', ['filter' => ['status' => 'enabled'], 'sort' => '-created_at'])
        ->assertJsonPath('data.file', null);

    $export = Export::query()->sole();

    expect($export)
        ->user_id->toBe($actor->id)
        ->exporter->toBe('system.user')
        ->format->toBe(ExportFormat::Csv)
        ->status->toBe(ExportStatus::Queued)
        ->locale->toBe('zh_CN')
        ->and($response->json('data.expires_at'))->toBe($export->created_at?->addHours(24)->toIso8601String());

    Queue::assertPushed(RunExport::class, fn (RunExport $job): bool => $job->exportId === $export->id && $job->timeout === 60);
});

it('defaults to xlsx and generates the file for the users of the list', function (): void {
    $actor = userWithDataScope('all', 'system.user.export');
    User::factory()->create(['username' => 'alice', 'status' => 'enabled']);
    User::factory()->disabled()->create(['username' => 'bob']);

    $this->actingAs($actor)
        ->postJson('/api/admin/system/users/export', ['filter' => ['status' => 'disabled']])
        ->assertCreated()
        ->assertJsonPath('data.format', 'xlsx');

    $export = Export::query()->sole();

    expect($export->status)->toBe(ExportStatus::Completed)
        ->and($export->row_count)->toBe(1)
        ->and(array_column(exportedRows($export), 1))->toBe(['bob']);
});

it('exports no more users than the actor can see in the list (self scope)', function (): void {
    $actor = userWithPermissions('system.user', 'system.user.export');
    $created = User::factory()->create(['created_by' => $actor->id]);
    User::factory()->count(2)->create();

    $listed = collect($this->actingAs($actor)->getJson('/api/admin/system/users?pageSize=100')->assertOk()->json('data.items'))->pluck('id')->sort()->values()->all();

    $this->actingAs($actor)->postJson('/api/admin/system/users/export', ['format' => 'csv'])->assertCreated();

    $exported = collect(exportedRows(Export::query()->sole()))->map(fn (array $row): int => (int) $row[0])->sort()->values()->all();

    expect($exported)->toBe($listed)->toBe(collect([$actor->id, $created->id])->sort()->values()->all());
});

it('rejects filters and sorts outside of the list whitelist, like the list does', function (array $payload, string $error): void {
    Queue::fake();

    $this->actingAs(userWithPermissions('system.user.export'))
        ->postJson('/api/admin/system/users/export', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect(Export::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'unknown filter (rejected as an undeclared field of the body)' => [['filter' => ['password' => 'x']], 'filter.password'],
    'unknown sort' => [['sort' => 'password'], 'sort'],
    'invalid status value' => [['filter' => ['status' => 'unknown']], 'filter.status'],
    'invalid time range' => [['filter' => ['created_at' => '2026-11-19,2026-11-20']], 'filter.created_at'],
    'invalid format' => [['format' => 'pdf'], 'format'],
    'pagination is not part of an export' => [['pageSize' => 10], 'pageSize'],
]);

it('limits the exports in progress per user', function (): void {
    config(['core.exports.max_active' => 2]);
    Queue::fake();
    $actor = userWithPermissions('system.user.export');
    Export::factory()->for($actor)->running()->create();
    Export::factory()->for($actor)->completed()->create();
    Export::factory()->failed()->create();

    $this->actingAs($actor)->postJson('/api/admin/system/users/export')->assertCreated();

    $this->actingAs($actor)
        ->postJson('/api/admin/system/users/export')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You can have at most 2 exports in progress. Please wait for them to finish.')
        ->assertJsonMissingPath('errors');

    expect(Export::query()->ownedBy($actor)->active()->count())->toBe(2);

    $this->actingAs(userWithPermissions('system.user.export'))->postJson('/api/admin/system/users/export')->assertCreated();
});

it('throttles starting exports per user with the export limiter', function (): void {
    config(['core.apps.admin.export_rate_limit' => 1, 'core.exports.max_active' => 10]);
    Queue::fake();
    $actor = userWithPermissions('system.user.export');

    $this->actingAs($actor)->postJson('/api/admin/system/users/export')->assertCreated();
    $this->actingAs($actor)->postJson('/api/admin/system/users/export')->assertTooManyRequests();
    $this->actingAs(userWithPermissions('system.user.export'))->postJson('/api/admin/system/users/export')->assertCreated();
});

it('notifies the actor when the export is ready', function (): void {
    $actor = userWithPermissions('system.user.export');

    $this->actingAs($actor)->postJson('/api/admin/system/users/export')->assertCreated();

    $export = Export::query()->sole();

    expect(Notification::query()->ownedBy($actor)->sole())
        ->type->toBe('message.export')
        ->and(Notification::query()->about($export)->count())->toBe(1);
});
