<?php

use App\Core\System\Actions\DeleteUser;
use App\Core\System\Models\{Attachment, Export, User};
use Illuminate\Support\Facades\{Artisan, Storage};

/*
|--------------------------------------------------------------------------
| Export：到期清理与随发起人删除（设计文档 §6.10）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * 带文件的导出。
 */
function exportWithFile(?User $owner = null): Export
{
    $export = Export::factory()->for($owner ?? User::factory()->create())->completed()->create();
    Attachment::factory()->ofType('text/csv', 'csv')->boundTo($export, Export::FILE_COLLECTION)->withFile()->create();

    return $export;
}

it('prunes the exports older than the ttl together with their files, whatever their status', function (): void {
    config(['core.exports.ttl' => 24]);
    $old = exportWithFile();
    $file = $old->file()->sole();
    $stuck = Export::factory()->create();
    Export::query()->whereKey([$old->id, $stuck->id])->update(['created_at' => now()->subHours(25)]);
    $fresh = exportWithFile();

    Artisan::call('model:prune', ['--model' => [Export::class]]);

    expect(Export::query()->pluck('id')->all())->toBe([$fresh->id])
        ->and(Attachment::query()->pluck('id')->all())->toBe([$fresh->file()->sole()->id]);
    Storage::disk('local')->assertMissing($file->path);
});

it('deletes the exports of a deleted user together with their files', function (): void {
    $owner = User::factory()->create();
    $export = exportWithFile($owner);
    $file = $export->file()->sole();
    $other = exportWithFile();

    resolve(DeleteUser::class)->handle($owner);

    expect(Export::query()->pluck('id')->all())->toBe([$other->id])
        ->and(Attachment::query()->whereKey($file->id)->exists())->toBeFalse();
    Storage::disk('local')->assertMissing($file->path);
});

it('expires ttl hours after its creation', function (): void {
    config(['core.exports.ttl' => 48]);
    $export = Export::factory()->create();

    expect($export->expiresAt()?->toIso8601String())->toBe($export->created_at?->addHours(48)->toIso8601String());
});
