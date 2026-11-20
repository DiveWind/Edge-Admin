<?php

use App\Core\Monitor\Actions\LogAudit;
use App\Core\Monitor\Models\Activity;
use App\Core\System\Enums\Status;
use App\Core\System\Models\User;
use Tests\Core\Monitor\Actions\Fixtures\AuditedUser;

/*
|--------------------------------------------------------------------------
| 通用的数据变更审计（Core 的扩展点“日志”，设计文档 §5.5）
|--------------------------------------------------------------------------
|
| 模型实现 Auditable 提供快照；日志名、description、按事件记录的字段、差异与大字段摘要都由 LogAudit 处理。
| 业务模块的完整用例见 tests/Modules/Content/Actions/ArticleActivityTest.php。
|
*/

beforeEach(function (): void {
    $this->actor = User::factory()->create(['username' => 'alice']);
    $this->subject = AuditedUser::query()->findOrFail(User::factory()->create(['username' => 'bob', 'name' => 'Bob'])->id);
});

it('logs the snapshot on creation, under the module segment of the morph alias, with large fields as digests', function (): void {
    $activity = resolve(LogAudit::class)->handle($this->actor, $this->subject, 'created');

    expect($activity)->toBeInstanceOf(Activity::class)
        ->and($activity?->log_name)->toBe('user')
        ->and($activity?->description)->toBe('user.created')
        ->and($activity?->event)->toBe('created')
        ->and($activity?->properties->all())->toBe([
            'username' => 'alice',
            'attributes' => ['username' => 'bob', 'name' => ['sha256' => hash('sha256', 'Bob'), 'length' => 3], 'status' => 'enabled'],
        ]);
});

it('logs only the changed fields of other events and skips them without changes', function (): void {
    $before = $this->subject->auditSnapshot();
    $this->subject->forceFill(['status' => Status::Disabled])->save();

    $activity = resolve(LogAudit::class)->handle($this->actor, $this->subject, 'disabled', $before);

    expect($activity?->properties->only(['old', 'attributes'])->all())->toBe([
        'old' => ['status' => 'enabled'],
        'attributes' => ['status' => 'disabled'],
    ]);

    expect(resolve(LogAudit::class)->handle($this->actor, $this->subject, 'updated', $this->subject->auditSnapshot()))->toBeNull();
});

it('logs only the event when deleting or restoring, and the last state when permanently deleting', function (): void {
    expect(resolve(LogAudit::class)->handle($this->actor, $this->subject, 'deleted')?->properties->keys()->all())->toBe(['username'])
        ->and(resolve(LogAudit::class)->handle($this->actor, $this->subject, 'restored')?->properties->keys()->all())->toBe(['username'])
        ->and(resolve(LogAudit::class)->handle($this->actor, $this->subject, 'force_deleted', $this->subject->auditSnapshot())?->getProperty('old'))
        ->toBe(['username' => 'bob', 'name' => ['sha256' => hash('sha256', 'Bob'), 'length' => 3], 'status' => 'enabled']);
});
