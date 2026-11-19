<?php

use App\Core\System\Models\{Export, User};
use Illuminate\Support\Facades\Gate;

/*
|--------------------------------------------------------------------------
| ExportPolicy：导出只属于发起人（设计文档 §6.10）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('lets only the owner view and delete an export, whatever their permissions', function (string $ability): void {
    $owner = User::factory()->create();
    $export = Export::factory()->for($owner)->create();

    expect(Gate::forUser($owner)->allows($ability, $export))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows($ability, $export))->toBeFalse()
        ->and(Gate::forUser(superAdminActor())->allows($ability, $export))->toBeFalse()
        ->and(Gate::forUser(userWithDataScope('all', 'system.user', 'system.user.export'))->allows($ability, $export))->toBeFalse();
})->with(['view', 'delete']);

it('lets every logged-in user list their own exports and denies everything else', function (): void {
    $owner = User::factory()->create();
    $export = Export::factory()->for($owner)->create();
    $gate = Gate::forUser($owner);

    expect($gate->allows('viewAny', Export::class))->toBeTrue()
        ->and($gate->allows('create', Export::class))->toBeFalse()
        ->and($gate->allows('update', $export))->toBeFalse()
        ->and($gate->allows('restore', $export))->toBeFalse()
        ->and($gate->allows('forceDelete', $export))->toBeFalse()
        ->and($gate->allows('deleteAny', Export::class))->toBeFalse()
        ->and($gate->allows('restoreAny', Export::class))->toBeFalse()
        ->and($gate->allows('forceDeleteAny', Export::class))->toBeFalse();
});
