<?php

use App\Core\System\Models\{Attachment, User};
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| AttachmentPolicy：访问权（未绑定 → 上传者；已绑定 → 委托给绑定对象；附件管理 → 数据范围）与删除（设计文档 §6.8）
|--------------------------------------------------------------------------
|
| 绑定对象用 User（头像）：能 view 某个用户的，是有 system.user 且该用户在数据范围内的人（UserPolicy）。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

describe('view', function (): void {
    it('lets only the uploader view an unbound attachment, besides attachment admins within the data scope', function (): void {
        $uploader = User::factory()->create();
        $attachment = Attachment::factory()->for($uploader, 'creator')->create();

        expect(Gate::forUser($uploader)->allows('view', $attachment))->toBeTrue()
            ->and(Gate::forUser(User::factory()->create())->allows('view', $attachment))->toBeFalse()
            ->and(Gate::forUser(userWithPermissions('system.attachment'))->allows('view', $attachment))->toBeFalse()
            ->and(Gate::forUser(userWithDataScope('all', 'system.attachment'))->allows('view', $attachment))->toBeTrue();
    });

    it('delegates a bound attachment to the policy of the owning record', function (): void {
        $owner = User::factory()->create();
        $attachment = Attachment::factory()->for($owner, 'creator')->boundTo($owner, User::AVATAR_COLLECTION)->create();
        $creatorOfOwner = userWithPermissions('system.user');
        $owner->forceFill(['created_by' => $creatorOfOwner->id])->save();

        expect(Gate::forUser(userWithDataScope('all', 'system.user'))->allows('view', $attachment))->toBeTrue()
            ->and(Gate::forUser($creatorOfOwner)->allows('view', $attachment->fresh()))->toBeTrue()
            ->and(Gate::forUser(userWithPermissions('system.user'))->allows('view', $attachment))->toBeFalse()
            ->and(Gate::forUser(userWithDataScope('all', 'system.role'))->allows('view', $attachment))->toBeFalse();
    });

    it('does not let the uploader keep access once the attachment belongs to a record they cannot view', function (): void {
        $uploader = User::factory()->create();
        $attachment = Attachment::factory()->for($uploader, 'creator')->boundTo(User::factory()->create(), 'files')->create();

        expect(Gate::forUser($uploader)->allows('view', $attachment))->toBeFalse();
    });

    it('lets attachment admins view bound attachments within their data scope', function (): void {
        $admin = userWithPermissions('system.attachment');
        $own = Attachment::factory()->for($admin, 'creator')->boundTo(User::factory()->create(), 'files')->create();
        $other = Attachment::factory()->boundTo(User::factory()->create(), 'files')->create();

        expect(Gate::forUser($admin)->allows('view', $own))->toBeTrue()
            ->and(Gate::forUser($admin)->allows('view', $other))->toBeFalse();
    });
});

it('lets every logged-in user upload', function (): void {
    expect(Gate::forUser(User::factory()->create())->allows('create', Attachment::class))->toBeTrue();
});

it('requires the system.attachment code to list', function (): void {
    expect(Gate::forUser(userWithPermissions('system.attachment'))->allows('viewAny', Attachment::class))->toBeTrue()
        ->and(Gate::forUser(userWithDataScope('all', 'system.attachment.delete'))->allows('viewAny', Attachment::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('viewAny', Attachment::class))->toBeFalse();
});

describe('delete', function (): void {
    it('requires the delete code and the data scope', function (): void {
        $actor = userWithPermissions('system.attachment.delete');

        expect(Gate::forUser($actor)->allows('delete', Attachment::factory()->for($actor, 'creator')->create()))->toBeTrue()
            ->and(Gate::forUser($actor)->allows('delete', Attachment::factory()->create()))->toBeFalse()
            ->and(Gate::forUser(userWithDataScope('all', 'system.attachment'))->allows('delete', Attachment::factory()->create()))->toBeFalse();
    });

    it('refuses bound attachments with a message, even for super admins', function (): void {
        $attachment = Attachment::factory()->boundTo(User::factory()->create(), User::AVATAR_COLLECTION)->create();

        expect(Gate::forUser(superAdminActor())->inspect('delete', $attachment)->message())->toBe('Attachments in use cannot be deleted.')
            ->and(Gate::forUser(superAdminActor())->allows('delete', $attachment))->toBeFalse();
    });
});

it('denies updates, restores and bulk operations, even for super admins', function (): void {
    $gate = Gate::forUser(superAdminActor());
    $attachment = Attachment::factory()->create();

    foreach (['update', 'restore', 'forceDelete'] as $ability) {
        expect($gate->allows($ability, $attachment))->toBeFalse($ability);
    }

    foreach (['deleteAny', 'restoreAny', 'forceDeleteAny'] as $ability) {
        expect($gate->allows($ability, Attachment::class))->toBeFalse($ability);
    }
});
