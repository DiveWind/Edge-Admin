<?php

use App\Core\System\Actions\{DeleteAttachment, DeleteUnboundAttachment, DeleteUser, StoreAttachment, SyncAttachments};
use App\Core\System\Models\{Attachment, User};
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| 附件的写入：保存、绑定、删除、级联与孤儿清理（设计文档 §6.8）
|--------------------------------------------------------------------------
|
| 绑定对象用 User（头像等用途）；User 使用 HasAttachments。
|
*/

beforeEach(function (): void {
    Storage::fake('local');
});

describe('StoreAttachment', function (): void {
    it('removes the stored file when the record cannot be written', function (): void {
        $ghost = User::factory()->make(['id' => 999]);

        expect(fn () => resolve(StoreAttachment::class)->handle(uploadedFile('photo.png', pngBytes()), $ghost))
            ->toThrow(QueryException::class);

        expect(Storage::disk('local')->allFiles())->toBe([])
            ->and(Attachment::query()->count())->toBe(0);
    });
});

describe('SyncAttachments', function (): void {
    it('binds the attachments to the collection of the record, in the submitted order', function (): void {
        $record = User::factory()->create();
        [$first, $second] = Attachment::factory()->count(2)->create();

        $synced = resolve(SyncAttachments::class)->handle($record, 'files', [$second->id, (string) $first->id]);

        expect($synced->modelKeys())->toBe([$second->id, $first->id])
            ->and($record->attachmentsIn('files')->pluck('id')->sort()->values()->all())->toBe([$first->id, $second->id])
            ->and($first->fresh())
            ->attachable_type->toBe('user')
            ->attachable_id->toBe($record->id)
            ->collection->toBe('files');
    });

    it('deletes the attachments that are no longer submitted, with their files after the commit', function (): void {
        $record = User::factory()->create();
        $kept = Attachment::factory()->boundTo($record, 'files')->withFile()->create();
        $removed = Attachment::factory()->boundTo($record, 'files')->withFile()->create();
        $otherCollection = Attachment::factory()->boundTo($record, 'avatar')->withFile()->create();

        resolve(SyncAttachments::class)->handle($record, 'files', [$kept->id]);

        expect($removed->fresh())->toBeNull()
            ->and($kept->fresh())->not->toBeNull()
            ->and($otherCollection->fresh())->not->toBeNull();
        Storage::disk('local')->assertMissing($removed->path);
        Storage::disk('local')->assertExists($kept->path);

        resolve(SyncAttachments::class)->handle($record, 'files', []);

        expect($kept->fresh())->toBeNull();
    });

    it('refuses to take an attachment bound to another record and changes nothing', function (): void {
        $record = User::factory()->create();
        $current = Attachment::factory()->boundTo($record, 'avatar')->create();
        $taken = Attachment::factory()->boundTo(User::factory()->create(), 'avatar')->create();

        expect(fn () => resolve(SyncAttachments::class)->handle($record, 'avatar', [$taken->id], 'avatar_id'))
            ->toThrow(ValidationException::class, 'The selected avatar is invalid.');

        expect($current->fresh())->not->toBeNull()
            ->and($taken->fresh()->attachable_id)->not->toBe($record->id);
    });

    it('rejects a missing attachment', function (): void {
        expect(fn () => resolve(SyncAttachments::class)->handle(User::factory()->create(), 'files', [999]))
            ->toThrow(ValidationException::class);
    });
});

describe('DeleteAttachment', function (): void {
    it('deletes the record and then the file', function (): void {
        $attachment = Attachment::factory()->withFile()->create();

        resolve(DeleteAttachment::class)->handle($attachment);

        expect(Attachment::query()->find($attachment->id))->toBeNull();
        Storage::disk('local')->assertMissing($attachment->path);
    });

    it('keeps the file when the surrounding transaction rolls back', function (): void {
        $attachment = Attachment::factory()->withFile()->create();

        try {
            DB::transaction(function () use ($attachment): void {
                resolve(DeleteAttachment::class)->handle($attachment);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            //
        }

        expect(Attachment::query()->find($attachment->id))->not->toBeNull();
        Storage::disk('local')->assertExists($attachment->path);
    });
});

it('deletes the attachments of a record together with the record', function (): void {
    syncBuiltinPermissions();
    $user = User::factory()->create();
    $avatar = Attachment::factory()->boundTo($user, User::AVATAR_COLLECTION)->withFile()->create();
    $unrelated = Attachment::factory()->for($user, 'creator')->withFile()->create();

    resolve(DeleteUser::class)->handle($user);

    expect($avatar->fresh())->toBeNull()
        ->and($unrelated->fresh())->not->toBeNull()
        ->and($unrelated->fresh()->created_by)->toBeNull();
    Storage::disk('local')->assertMissing($avatar->path);
    Storage::disk('local')->assertExists($unrelated->path);
});

it('prunes the orphans older than the configured hours, with their files', function (): void {
    config(['core.attachments.orphan_ttl' => 24]);
    $old = Attachment::factory()->withFile()->create(['created_at' => now()->subHours(25)]);
    $recent = Attachment::factory()->withFile()->create(['created_at' => now()->subHours(23)]);
    $bound = Attachment::factory()->boundTo(User::factory()->create(), 'files')->withFile()->create(['created_at' => now()->subDays(30)]);

    $this->artisan('model:prune', ['--model' => [Attachment::class]])->assertSuccessful();

    expect(Attachment::query()->pluck('id')->sort()->values()->all())->toBe([$recent->id, $bound->id]);
    Storage::disk('local')->assertMissing($old->path);
    Storage::disk('local')->assertExists($recent->path);
    Storage::disk('local')->assertExists($bound->path);
});

describe('DeleteUnboundAttachment', function (): void {
    it('skips an orphan that was bound after the prune read it, and keeps its file', function (): void {
        $owner = User::factory()->create();
        $attachment = Attachment::factory()->withFile()->create(['created_by' => $owner->id, 'created_at' => now()->subDays(3)]);

        // model:prune 已经读出这一批；用户随后在表单中提交并绑定了它
        $stale = (new Attachment)->prunable()->sole();
        resolve(SyncAttachments::class)->handle($owner, User::AVATAR_COLLECTION, [$attachment->id]);

        expect($stale->prune())->toBeFalse();

        $this->assertModelExists($attachment);
        Storage::disk('local')->assertExists($attachment->path);
    });

    it('returns 422 to attachment management for an attachment bound after the policy check, and keeps it', function (): void {
        $attachment = Attachment::factory()->withFile()->create();
        $stale = Attachment::query()->findOrFail($attachment->id);
        resolve(SyncAttachments::class)->handle(User::factory()->create(), 'files', [$attachment->id]);

        expect(fn () => resolve(DeleteUnboundAttachment::class)->handle($stale))
            ->toThrow(BusinessRuleViolation::class, 'Attachments in use cannot be deleted.');

        $this->assertModelExists($attachment);
        Storage::disk('local')->assertExists($attachment->path);
    });

    it('deletes an attachment that is still unbound, with its file', function (): void {
        $attachment = Attachment::factory()->withFile()->create();

        expect(resolve(DeleteUnboundAttachment::class)->handle($attachment))->toBeTrue();

        $this->assertModelMissing($attachment);
        Storage::disk('local')->assertMissing($attachment->path);
    });

    it('returns 404 to attachment management for an attachment deleted meanwhile, and skips it when pruning', function (): void {
        $attachment = Attachment::factory()->create();
        resolve(DeleteAttachment::class)->handle(Attachment::query()->findOrFail($attachment->id));

        expect(resolve(DeleteUnboundAttachment::class)->handle($attachment, skipIfBound: true))->toBeFalse()
            ->and(fn () => resolve(DeleteUnboundAttachment::class)->handle($attachment))->toThrow(ModelNotFoundException::class);
    });
});
