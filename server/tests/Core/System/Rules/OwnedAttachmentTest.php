<?php

use App\Core\System\Models\{Attachment, User};
use App\Core\System\Rules\OwnedAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| OwnedAttachment：引用附件时校验归属（设计文档 §6.8、§9）
|--------------------------------------------------------------------------
|
| 只能用自己上传的未绑定附件，或已经绑定在这条记录这个用途上的附件。绑定对象用 User（头像用途）。
|
*/

/**
 * 用 OwnedAttachment 校验一个附件 id，返回错误信息（通过时为空数组）。
 *
 * @param  list<string>  $mimeTypes
 * @return list<string>
 */
function ownedAttachmentErrors(User $actor, mixed $value, ?Model $record = null, ?string $collection = null, array $mimeTypes = []): array
{
    return Validator::make(['attachment_id' => $value], ['attachment_id' => [new OwnedAttachment($actor, $record, $collection, $mimeTypes)]])
        ->errors()->get('attachment_id');
}

it('accepts an unbound attachment uploaded by the actor, as an integer or a numeric string', function (): void {
    $actor = User::factory()->create();
    $attachment = Attachment::factory()->for($actor, 'creator')->create();

    expect(ownedAttachmentErrors($actor, $attachment->id))->toBe([])
        ->and(ownedAttachmentErrors($actor, (string) $attachment->id))->toBe([]);
});

it('accepts the attachment already bound to the same record and collection', function (): void {
    $record = User::factory()->create();
    $attachment = Attachment::factory()->boundTo($record, 'avatar')->create();

    expect(ownedAttachmentErrors(User::factory()->create(), $attachment->id, $record, 'avatar'))->toBe([]);
});

it('rejects anything else with the same message, whatever the reason', function (Closure $scenario): void {
    $actor = User::factory()->create();
    [$value, $record, $collection] = $scenario($actor);

    expect(ownedAttachmentErrors($actor, $value, $record, $collection))->toBe(['The selected attachment id is invalid.']);
})->with([
    'missing attachment' => [fn (User $actor): array => [999, null, null]],
    'not an id' => [fn (User $actor): array => ['1 or 1=1', null, null]],
    'unbound attachment of someone else' => [fn (User $actor): array => [Attachment::factory()->create()->id, null, null]],
    'own attachment bound to another record' => [fn (User $actor): array => [Attachment::factory()->for($actor, 'creator')->boundTo(User::factory()->create(), 'avatar')->create()->id, $actor, 'avatar']],
    'bound to the record in another collection' => [fn (User $actor): array => [Attachment::factory()->boundTo($actor, 'files')->create()->id, $actor, 'avatar']],
    'bound attachment while creating a record' => [fn (User $actor): array => [Attachment::factory()->for($actor, 'creator')->boundTo($actor, 'avatar')->create()->id, null, 'avatar']],
]);

it('restricts the MIME types when asked', function (): void {
    $actor = User::factory()->create();
    $pdf = Attachment::factory()->for($actor, 'creator')->ofType('application/pdf', 'pdf')->create();
    $png = Attachment::factory()->for($actor, 'creator')->create();

    expect(ownedAttachmentErrors($actor, $pdf->id, mimeTypes: Attachment::PREVIEWABLE_MIME_TYPES))
        ->toBe(['The attachment id field must be a file of type: image/jpeg, image/png, image/gif, image/webp.'])
        ->and(ownedAttachmentErrors($actor, $png->id, mimeTypes: Attachment::PREVIEWABLE_MIME_TYPES))->toBe([]);
});
