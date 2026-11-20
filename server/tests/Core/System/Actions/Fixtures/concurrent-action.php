<?php

/*
|--------------------------------------------------------------------------
| ConcurrentActionsTest 的子进程：在独立的进程与数据库连接中准备数据、执行一个加锁的 Action、读取结果
|--------------------------------------------------------------------------
|
| php concurrent-action.php setup <场景>                      建数据并提交，输出各记录的 id（JSON）
| php concurrent-action.php run <动作> <JSON 参数> <标记文件> <持锁毫秒> [<开始文件>]
|                                                             执行 Action：第一条加锁语句（FOR UPDATE / 共享锁；分配角色为角色行的共享锁）执行后，把它写入
|                                                             标记文件并停留给定的毫秒数（事务中已拿到的锁一直持有），输出结果与耗时；
|                                                             给了开始文件时，启动完成后等它出现再执行（进程启动需要数百毫秒）
| php concurrent-action.php state <场景> <JSON 参数>           输出数据的最终状态（JSON）
| php concurrent-action.php cleanup                           清空全部表（migrations 除外）
|
| 数据库连接与其他环境变量继承自父进程（phpunit.xml 与 DB_* 覆盖）。
|
*/

use App\Core\System\Actions\{ChangeUserStatus, CreatePermission, DeletePermission, DeleteRole, DeleteUnboundAttachment, DeleteUser, SyncAttachments, SyncUserRoles, UpdateUser};
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Attachment, Permission, Role, User};
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../../../../vendor/autoload.php';

$app = require __DIR__.'/../../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * @return array<string, mixed>
 */
function setUp(string $scenario): array
{
    return match ($scenario) {
        'super-admins' => (function (): array {
            $role = Role::factory()->superAdmin()->create();

            return ['a' => User::factory()->create()->assignRole($role)->id, 'b' => User::factory()->create()->assignRole($role)->id];
        })(),
        'role' => [
            'role' => Role::factory()->create()->name,
            'user' => User::factory()->create()->id,
            'actor' => User::factory()->create()->assignRole(Role::factory()->superAdmin()->create())->id,
        ],
        'node' => ['node' => Permission::factory()->catalog()->create(['name' => 'race', 'path' => '/race'])->id],
        'attachment' => ['attachment' => Attachment::factory()->create()->id, 'user' => User::factory()->create()->id],
        default => throw new InvalidArgumentException("Unknown scenario {$scenario}"),
    };
}

/**
 * @param  array<string, mixed>  $ids
 * @return array<string, mixed>
 */
function state(string $scenario, array $ids): array
{
    return match ($scenario) {
        'super-admins' => [
            'enabled_super_admins' => User::query()
                ->where('status', Status::Enabled)
                ->whereHas('roles', fn (Builder $query) => $query->where('name', Role::SUPER_ADMIN))
                ->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        ],
        'role' => [
            'role_exists' => Role::query()->where('name', $ids['role'])->exists(),
            'assigned' => DB::table('model_has_roles')->where('model_id', $ids['user'])->exists(),
        ],
        'node' => [
            'node_exists' => Permission::query()->whereKey($ids['node'])->exists(),
            'children' => Permission::query()->where('parent_id', $ids['node'])->count(),
        ],
        'attachment' => [
            'attachment_exists' => Attachment::query()->whereKey($ids['attachment'])->exists(),
            'bound' => Attachment::query()->whereKey($ids['attachment'])->whereNotNull('attachable_type')->exists(),
        ],
        default => throw new InvalidArgumentException("Unknown scenario {$scenario}"),
    };
}

/**
 * @param  array<string, mixed>  $p
 */
function run(string $action, array $p): mixed
{
    $user = fn (string $key): User => User::query()->with('roles')->findOrFail($p[$key]);

    return match ($action) {
        'disable' => resolve(ChangeUserStatus::class)->handle($user('target'), Status::Disabled, $user('actor')),
        'delete' => resolve(DeleteUser::class)->handle($user('target')),
        'demote' => resolve(UpdateUser::class)->handle($user('target'), [], [], $user('actor')),
        'delete-role' => resolve(DeleteRole::class)->handle(Role::query()->where('name', $p['role'])->firstOrFail()),
        'assign-role' => resolve(SyncUserRoles::class)->handle($user('user'), [$p['role']], $user('actor')),
        'delete-node' => resolve(DeletePermission::class)->handle(Permission::query()->findOrFail($p['node'])),
        'create-child' => resolve(CreatePermission::class)->handle([
            'parent_id' => $p['node'], 'type' => 'menu', 'name' => 'race.page', 'title' => 'Race', 'path' => '/race/page', 'component' => '/race/page/index',
        ]),
        // 清理孤儿（model:prune → Attachment::prune()）：已被绑定的跳过，返回 false
        'prune-attachment' => resolve(DeleteUnboundAttachment::class)->handle(Attachment::query()->findOrFail($p['attachment']), skipIfBound: true),
        'bind-attachment' => resolve(SyncAttachments::class)->handle($user('user'), 'avatar', [$p['attachment']]),
        default => throw new InvalidArgumentException("Unknown action {$action}"),
    };
}

$mode = $argv[1] ?? '';

if ($mode === 'cleanup') {
    Schema::withoutForeignKeyConstraints(function (): void {
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if ($table !== 'migrations') {
                DB::table($table)->truncate();
            }
        }
    });

    return;
}

if ($mode === 'setup') {
    echo json_encode(setUp($argv[2]));

    return;
}

/** @var array<string, mixed> $parameters */
$parameters = json_decode($argv[3] ?? '{}', true);

if ($mode === 'state') {
    echo json_encode(state($argv[2], $parameters));

    return;
}

[$action, $marker, $holdMs, $go] = [$argv[2], $argv[4], (int) $argv[5], $argv[6] ?? null];
$locked = false;

for ($deadline = microtime(true) + 30; $go !== null && ! file_exists($go) && microtime(true) < $deadline;) {
    Sleep::usleep(2_000);
}

// 停留在哪条加锁语句之后：分配角色停在角色行的共享锁（它之前的 super-admin 行锁与删除角色无关），其余停在第一条加锁语句
$holdOn = $action === 'assign-role' ? '/(for share|lock in share mode)$/' : '/(for update|for share|lock in share mode)$/';

DB::listen(function (QueryExecuted $query) use (&$locked, $marker, $holdMs, $holdOn): void {
    if ($locked || preg_match($holdOn, $query->sql) !== 1) {
        return;
    }

    $locked = true;
    file_put_contents($marker, $query->sql);
    Sleep::usleep($holdMs * 1000);
});

$started = hrtime(true);

try {
    $value = run($action, $parameters);
    $result = ['result' => $value === false ? 'skipped' : 'ok'];
} catch (ValidationException|BusinessRuleViolation $e) {
    $result = ['result' => 'rejected', 'message' => $e->getMessage()];
} catch (Throwable $e) {
    $result = ['result' => 'error', 'message' => $e::class.': '.$e->getMessage()];
}

echo json_encode([...$result, 'elapsed_ms' => intdiv(hrtime(true) - $started, 1_000_000)]);
