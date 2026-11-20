<?php

use App\Core\Message\Models\Notification;
use App\Core\System\Actions\GenerateExport;
use App\Core\System\Contracts\Exporter;
use App\Core\System\Enums\{ExportFormat, ExportStatus};
use App\Core\System\Jobs\RunExport;
use App\Core\System\Models\{Attachment, Export, Role, User};
use App\Core\System\Services\ExporterRegistry;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\Attributes\{FailOnTimeout, Tries};
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;

/*
|--------------------------------------------------------------------------
| 生成导出文件：GenerateExport / RunExport（设计文档 §6.10）
|--------------------------------------------------------------------------
|
| 存储盘用 Storage::fake()；读取生成的文件用 simple-excel 的读取器。XLSX 中以 `=` 开头的字符串单元格直接检查工作表的 XML
| （openspout 的读取器把这种内联字符串读成空值）。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    Storage::fake('local');
});

/**
 * 排队中的用户列表导出，发起人可以看到全部用户并有导出权限。
 *
 * @param  array<string, mixed>  $attributes
 */
function queuedUserExport(array $attributes = [], ?User $owner = null): Export
{
    return Export::factory()->for($owner ?? userWithDataScope('all', 'system.user.export'))->create($attributes);
}

/**
 * 导出文件的全部行（含表头）。
 *
 * @return list<list<mixed>>
 */
function exportFileRows(Export $export): array
{
    $file = $export->file()->sole();

    return SimpleExcelReader::create(Storage::disk($file->disk)->path($file->path), $file->extension)->noHeaderRow()->getRows()->values()->all();
}

/**
 * 登记测试用的导出类 `test.items`（权限码沿用 system.user.export），返回导出记录。
 *
 * @param  Closure(Model): array<string, string|int|float|bool|null>  $map
 */
function registerTestExporter(Closure $map): void
{
    $exporter = new readonly class($map) implements Exporter
    {
        public function __construct(private Closure $mapper) {}

        public function title(): string
        {
            return 'Items';
        }

        public function permission(): string
        {
            return 'system.user.export';
        }

        public function columns(): array
        {
            return ['value' => 'Value', 'number' => 'Number'];
        }

        public function query(User $actor, array $parameters): Builder
        {
            return User::query()->whereKey($actor->id);
        }

        public function map(Model $row): array
        {
            return ($this->mapper)($row);
        }
    };

    app()->instance($exporter::class, $exporter);
    resolve(ExporterRegistry::class)->register(['test.items' => $exporter::class]);
}

describe('file', function (): void {
    it('writes a CSV with a BOM, translated headers and the mapped rows, and binds it to the export', function (): void {
        $this->travelTo(now()->setDateTime(2026, 11, 19, 8, 30, 0));
        $owner = userWithDataScope('all', 'system.user.export');
        $owner->forceFill(['username' => 'owner'])->save();
        $role = Role::factory()->create(['title' => 'Editors']);
        User::factory()->create(['username' => 'alice', 'name' => 'Alice', 'email' => null])->assignRole($role);
        $export = queuedUserExport(['format' => ExportFormat::Csv, 'locale' => 'zh_CN', 'params' => ['filter' => ['username' => 'alice']]], $owner);

        resolve(GenerateExport::class)->handle($export->id);

        $export->refresh();
        $file = $export->file()->sole();
        $contents = Storage::disk('local')->get($file->path);

        expect($export)
            ->status->toBe(ExportStatus::Completed)
            ->row_count->toBe(1)
            ->started_at->not->toBeNull()
            ->finished_at->not->toBeNull()
            ->and($file)
            ->name->toBe('用户-20261119-083000.csv')
            ->mime_type->toBe('text/csv')
            ->extension->toBe('csv')
            ->created_by->toBe($owner->id)
            ->size->toBe(strlen((string) $contents))
            ->sha256->toBe(hash('sha256', (string) $contents))
            ->and($file->path)->toMatch('#^exports/2026/11/19/[A-Za-z0-9]{40}\.csv$#')
            ->and(str_starts_with((string) $contents, "\xEF\xBB\xBF"))->toBeTrue()
            ->and(exportFileRows($export))->toBe([
                ['ID', '用户名', '姓名', '邮箱', '状态', '角色', '创建时间'],
                [(string) User::query()->where('username', 'alice')->value('id'), 'alice', 'Alice', '', '启用', 'Editors', '2026-11-19 08:30:00'],
            ]);
    });

    it('writes an XLSX that keeps formula-like strings as plain text', function (): void {
        registerTestExporter(fn (): array => ['value' => '=HYPERLINK("https://evil.example")', 'number' => -5]);
        $export = queuedUserExport(['exporter' => 'test.items', 'format' => ExportFormat::Xlsx]);

        resolve(GenerateExport::class)->handle($export->id);

        $file = $export->file()->sole();
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($file->path));
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        expect($file->mime_type)->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->and($sheet)->not->toContain('<f>')
            ->toContain('t="inlineStr"><is><t>=HYPERLINK(&quot;https://evil.example&quot;)</t></is>')
            ->toContain('<v>-5</v>')
            ->and(exportFileRows($export)[0])->toBe(['Value', 'Number']);
    });

    it('prefixes formula-like strings with a quote in CSV', function (string $value): void {
        registerTestExporter(fn (): array => ['value' => $value, 'number' => -5]);
        $export = queuedUserExport(['exporter' => 'test.items', 'format' => ExportFormat::Csv]);

        resolve(GenerateExport::class)->handle($export->id);

        expect(exportFileRows($export)[1])->toBe(["'".$value, '-5']);
    })->with(['=1+1', '+1', '-1', '@SUM(A1)']);

    it('reads the rows in chunks and keeps the order of the list sort', function (): void {
        config(['core.system.export.chunk_size' => 2]);
        foreach (['dave', 'carol', 'erin', 'bob', 'alice'] as $username) {
            User::factory()->create(['username' => $username, 'name' => 'same']);
        }
        $export = queuedUserExport(['format' => ExportFormat::Csv, 'params' => ['filter' => ['name' => 'same'], 'sort' => 'username']]);

        resolve(GenerateExport::class)->handle($export->id);

        expect(array_column(array_slice(exportFileRows($export), 1), 1))->toBe(['alice', 'bob', 'carol', 'dave', 'erin'])
            ->and($export->refresh()->row_count)->toBe(5);
    });

    it('removes its temporary file', function (): void {
        $before = glob(sys_get_temp_dir().'/core-export-*') ?: [];

        resolve(GenerateExport::class)->handle(queuedUserExport()->id);

        expect(array_values(array_diff(glob(sys_get_temp_dir().'/core-export-*') ?: [], $before)))->toBe([]);
    });
});

describe('notifications', function (): void {
    it('notifies the owner with a link to the exports page when the export is completed', function (): void {
        $export = queuedUserExport(['locale' => 'zh_CN']);

        resolve(GenerateExport::class)->handle($export->id);

        $notification = Notification::query()->ownedBy($export->user()->sole())->sole();

        expect($notification->type)->toBe('export_finished')
            ->and($notification->data)->toMatchArray([
                'title' => 'message.notification.exportCompleted',
                'level' => 'success',
                'link' => '/exports',
                'params' => ['title' => '用户', 'rows' => 1],
            ]);
    });

    it('fails without writing data when the owner lost the export permission or was disabled', function (Closure $revoke): void {
        $export = queuedUserExport();
        $revoke($export->user()->sole());

        resolve(GenerateExport::class)->handle($export->id);

        expect($export->refresh()->status)->toBe(ExportStatus::Failed)
            ->and(Attachment::query()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    })->with([
        'permission revoked' => fn (User $owner) => $owner->roles()->sole()->syncPermissions([]),
        'role disabled' => fn (User $owner) => $owner->roles()->sole()->update(['status' => 'disabled']),
        'account disabled' => fn (User $owner) => $owner->update(['status' => 'disabled']),
    ]);

    it('marks the export as failed and notifies the owner when the job fails', function (): void {
        config(['core.system.export.link' => '/exports/{id}']);
        registerTestExporter(fn (): array => throw new RuntimeException('boom'));
        $export = queuedUserExport(['exporter' => 'test.items']);

        expect(fn (): PendingDispatch => dispatch(new RunExport($export->id)))->toThrow(RuntimeException::class, 'boom');

        $notification = Notification::query()->ownedBy($export->user()->sole())->sole();

        expect($export->refresh())->status->toBe(ExportStatus::Failed)->finished_at->not->toBeNull()
            ->and(Attachment::query()->count())->toBe(0)
            ->and($notification->data)->toMatchArray([
                'title' => 'message.notification.exportFailed',
                'level' => 'error',
                'link' => "/exports/{$export->id}",
                'params' => ['title' => 'Items', 'rows' => null],
            ]);
    });
});

describe('cancellation and idempotency', function (): void {
    it('does nothing for an export that was deleted or already handled', function (Closure $prepare): void {
        $export = queuedUserExport();
        $prepare($export);

        resolve(GenerateExport::class)->handle($export->id);

        expect(Attachment::query()->count())->toBe(0)
            ->and(Notification::query()->count())->toBe(0);
    })->with([
        'deleted' => fn (Export $export) => $export->delete(),
        'running' => fn (Export $export) => $export->forceFill(['status' => ExportStatus::Running])->save(),
        'completed' => fn (Export $export) => $export->forceFill(['status' => ExportStatus::Completed])->save(),
    ]);

    it('removes the written file when the export is deleted while it runs', function (): void {
        registerTestExporter(function (Model $row): array {
            Export::query()->delete();

            return ['value' => 'x', 'number' => 1];
        });
        $export = queuedUserExport(['exporter' => 'test.items']);

        resolve(GenerateExport::class)->handle($export->id);

        expect(Export::query()->count())->toBe(0)
            ->and(Attachment::query()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([])
            ->and(Notification::query()->count())->toBe(0);
    });

    it('does not mark a completed export as failed', function (): void {
        $export = Export::factory()->completed()->create();

        new RunExport($export->id)->failed(new RuntimeException('late'));

        expect($export->refresh()->status)->toBe(ExportStatus::Completed)
            ->and(Notification::query()->count())->toBe(0);
    });
});

it('uses the configured timeout and fails on timeout without retries', function (): void {
    config(['core.system.export.timeout' => 45]);

    $job = new RunExport(1);

    expect($job->timeout)->toBe(45)
        ->and(new ReflectionClass($job)->getAttributes(Tries::class)[0]->getArguments())->toBe([1])
        ->and(new ReflectionClass($job)->getAttributes(FailOnTimeout::class))->toHaveCount(1);
});
