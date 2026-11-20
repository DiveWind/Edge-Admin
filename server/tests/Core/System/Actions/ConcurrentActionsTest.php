<?php

use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\{DB, File, Process, Schema};
use Illuminate\Support\Sleep;

/*
|--------------------------------------------------------------------------
| 加锁的 Action：两个进程真正并发（设计文档 §8.6、§14）
|--------------------------------------------------------------------------
|
| 只在 MySQL / MariaDB（REPEATABLE READ）与 PostgreSQL（READ COMMITTED）上运行：SQLite 同一时间只有一个写事务，没有行锁可测。
| 数据必须提交才能被另一个进程看到，因此不在测试的事务中准备数据：子进程（Fixtures/concurrent-action.php）建数据、执行 Action、
| 读取结果，每个用例结束时清空全部表。
|
| 两个进程同时启动；第一个进程执行到第一条加锁语句（FOR UPDATE / 共享锁）后停留 HOLD_MS 毫秒、继续持有锁，第二个进程
| （已启动完毕、等待开始文件）在此期间开始执行：
| 它必须等第一个进程提交后才能拿到冲突的锁，并且看到第一个进程提交的结果，因此被 422 拒绝或跳过（不是 500，不是死锁）。
| 锁被去掉、或在拿锁之前先读了要复核的行（MySQL 的一致性快照会早于对方的提交），两个进程都会成功，断言失败。
|
*/

const HOLD_MS = 800;

/**
 * 在子进程中执行 concurrent-action.php。
 */
function concurrentAction(string ...$arguments): InvokedProcess
{
    return Process::timeout(60)->start([PHP_BINARY, __DIR__.'/Fixtures/concurrent-action.php', ...$arguments]);
}

/**
 * 子进程的 JSON 输出（进程失败时带上错误输出）。
 *
 * @return array<array-key, mixed>
 */
function concurrentOutput(InvokedProcess $process): array
{
    $result = $process->wait();

    expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

    return (array) json_decode($result->output(), true);
}

/**
 * 两个进程先后执行 $holder、$waiter（各为 [动作, 参数]），返回两者的结果。
 *
 * @param  array{string, array<string, mixed>}  $holder
 * @param  array{string, array<string, mixed>}  $waiter
 * @return array{array<array-key, mixed>, array<array-key, mixed>}
 */
function race(array $holder, array $waiter): array
{
    $marker = sys_get_temp_dir().'/concurrent-action-'.bin2hex(random_bytes(8));

    try {
        $second = concurrentAction('run', $waiter[0], (string) json_encode($waiter[1]), $marker.'-waiter', '0', $marker);
        $first = concurrentAction('run', $holder[0], (string) json_encode($holder[1]), $marker, (string) HOLD_MS);

        $deadline = microtime(true) + 30;
        while (! file_exists($marker) && $first->running() && microtime(true) < $deadline) {
            Sleep::usleep(10_000);
        }

        expect(file_exists($marker))->toBeTrue('The first process took no lock: '.$first->wait()->output());

        return [concurrentOutput($first), concurrentOutput($second)];
    } finally {
        File::delete($marker);
        File::delete($marker.'-waiter');
    }
}

beforeEach(function (): void {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite has no row locks: run on MySQL, MariaDB or PostgreSQL.');
    }

    // 触发迁移（LazilyRefreshDatabase）；之后本进程不再读写这些表，数据都由子进程提交
    expect(Schema::hasTable('users'))->toBeTrue();
});

afterEach(function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        concurrentOutput(concurrentAction('cleanup'));
    }
});

it('keeps exactly one enabled super admin when two super admins act on each other at the same time', function (string $first, string $second): void {
    ['a' => $a, 'b' => $b] = concurrentOutput(concurrentAction('setup', 'super-admins'));

    [$holder, $waiter] = race([$first, ['target' => $b, 'actor' => $a]], [$second, ['target' => $a, 'actor' => $b]]);

    expect($holder['result'])->toBe('ok', json_encode($holder))
        ->and($waiter['result'])->toBe('rejected', json_encode($waiter))
        ->and($waiter['elapsed_ms'])->toBeGreaterThan(HOLD_MS / 4)
        ->and(concurrentOutput(concurrentAction('state', 'super-admins', '{}')))->toBe(['enabled_super_admins' => [$a]]);
})->with([
    'both disable' => ['disable', 'disable'],
    'both delete' => ['delete', 'delete'],
    'both remove the super admin role' => ['demote', 'demote'],
    'delete against disable' => ['delete', 'disable'],
    'disable against remove the super admin role' => ['disable', 'demote'],
]);

it('does not delete a role that is being assigned, nor assign a role that is being deleted', function (string $first, string $second, array $state): void {
    $ids = concurrentOutput(concurrentAction('setup', 'role'));

    [$holder, $waiter] = race([$first, $ids], [$second, $ids]);

    expect($holder['result'])->toBe('ok', json_encode($holder))
        ->and($waiter['result'])->toBe('rejected', json_encode($waiter))
        ->and($waiter['elapsed_ms'])->toBeGreaterThan(HOLD_MS / 4)
        ->and(concurrentOutput(concurrentAction('state', 'role', (string) json_encode($ids))))->toBe($state);
})->with([
    'assign, then delete' => ['assign-role', 'delete-role', ['role_exists' => true, 'assigned' => true]],
    'delete, then assign' => ['delete-role', 'assign-role', ['role_exists' => false, 'assigned' => false]],
]);

it('does not delete a node that is getting a child, nor add a child to a node that is being deleted', function (string $first, string $second, array $state): void {
    $ids = concurrentOutput(concurrentAction('setup', 'node'));

    [$holder, $waiter] = race([$first, $ids], [$second, $ids]);

    expect($holder['result'])->toBe('ok', json_encode($holder))
        ->and($waiter['result'])->toBe('rejected', json_encode($waiter))
        ->and($waiter['elapsed_ms'])->toBeGreaterThan(HOLD_MS / 4)
        ->and(concurrentOutput(concurrentAction('state', 'node', (string) json_encode($ids))))->toBe($state);
})->with([
    'add a child, then delete' => ['create-child', 'delete-node', ['node_exists' => true, 'children' => 1]],
    'delete, then add a child' => ['delete-node', 'create-child', ['node_exists' => false, 'children' => 0]],
]);

it('does not delete an orphan attachment that is being bound, nor bind an attachment that is being deleted', function (string $first, string $second, string $waiterResult, array $state): void {
    $ids = concurrentOutput(concurrentAction('setup', 'attachment'));

    [$holder, $waiter] = race([$first, $ids], [$second, $ids]);

    expect($holder['result'])->toBe('ok', json_encode($holder))
        ->and($waiter['result'])->toBe($waiterResult, json_encode($waiter))
        ->and($waiter['elapsed_ms'])->toBeGreaterThan(HOLD_MS / 4)
        ->and(concurrentOutput(concurrentAction('state', 'attachment', (string) json_encode($ids))))->toBe($state);
})->with([
    // 清理读出孤儿之后它才被绑定：锁内复核时已绑定，跳过（model:prune 不报错）
    'bind, then prune' => ['bind-attachment', 'prune-attachment', 'skipped', ['attachment_exists' => true, 'bound' => true]],
    'prune, then bind' => ['prune-attachment', 'bind-attachment', 'rejected', ['attachment_exists' => false, 'bound' => false]],
]);
