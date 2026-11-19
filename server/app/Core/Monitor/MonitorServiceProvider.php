<?php

namespace App\Core\Monitor;

use App\Core\Monitor\Checks\BackupsCheck;
use App\Core\Monitor\Enums\{LoginEvent, LoginFailureReason};
use App\Core\Monitor\Listeners\RecordLoginActivity;
use App\Core\Monitor\Models\{Activity, FailedJob, LoginLog, OperationLog, ScheduledTask, ScheduledTaskLog, Session};
use App\Core\Monitor\Policies\PersonalAccessTokenPolicy;
use App\Core\System\Contracts\MenuRegistry;
use App\Core\System\Services\EnumRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\{Event, Gate};
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\{CacheCheck, DatabaseCheck, DatabaseConnectionCountCheck, DebugModeCheck, EnvironmentCheck, OptimizedAppCheck, QueueCheck, ScheduleCheck, UsedDiskSpaceCheck};
use Spatie\Health\Facades\Health;

/**
 * Monitor（内核模块）：职责与对外提供的能力见同目录的 README.md。
 *
 * 注册本模块的契约绑定、多态别名、命令、事件监听、语言包与视图，并通过 Core 的扩展点接入（设计文档 §4.3、§5.5）。
 * 多态别名与契约绑定由 core:make-model、core:make-contract 自动登记。
 *
 * - 菜单与权限节点（resources/menus.php）注册到 System 的 MenuRegistry；登录日志的枚举登记到 EnumRegistry（选项接口，§6.7）；
 * - 登录日志：RecordLoginActivity 订阅认证事件（设计文档 §7.3）；
 * - Sanctum 的 PersonalAccessToken 不在 app/ 中，Policy 无法按命名推断，这里显式绑定 PersonalAccessTokenPolicy；
 * - 健康检查的检查项（spatie/laravel-health，阈值见 config('core.monitor.health')，设计文档 §6.6）：只在启动时登记到 Health 单例，
 *   不保存请求数据；
 * - 备份（spatie/laravel-backup，设计文档 §6.10）：菜单 monitor.backup，调度在 routes/console.php，配置在 config/backup.php。
 *
 * 操作日志的中间件在后台入口（App\Http\Admin\Middleware\RecordOperationLog）；过期日志、执行记录的清理与健康检查、心跳在 routes/console.php 中调度。
 */
class MonitorServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(MenuRegistry $menus, EnumRegistry $enums): void
    {
        Relation::morphMap([
            'activity' => Activity::class,
            'operation_log' => OperationLog::class,
            'login_log' => LoginLog::class,
            'session' => Session::class,
            'scheduled_task' => ScheduledTask::class,
            'scheduled_task_log' => ScheduledTaskLog::class,
            'failed_job' => FailedJob::class,
        ]);

        $menus->register(require __DIR__.'/resources/menus.php');
        $enums->register([
            'login_event' => LoginEvent::class,
            'login_failure_reason' => LoginFailureReason::class,
        ]);

        Gate::policy(PersonalAccessToken::class, PersonalAccessTokenPolicy::class);

        Event::subscribe(RecordLoginActivity::class);

        Health::checks($this->healthChecks());
    }

    /**
     * 健康检查项（设计文档 §6.6）：数据库连接数只在 MySQL / MariaDB / PostgreSQL 下注册，磁盘使用率依赖 `df`（Windows 下默认关闭），
     * 备份（Backups，§6.10）只在备份调度开启时注册。
     *
     * @return list<Check>
     */
    protected function healthChecks(): array
    {
        $health = 'core.monitor.health.';
        $connection = config()->string('database.default');

        return array_values(array_filter([
            DatabaseCheck::new(),
            in_array(config("database.connections.{$connection}.driver"), ['mysql', 'mariadb', 'pgsql'], true)
                ? DatabaseConnectionCountCheck::new()
                    ->warnWhenMoreConnectionsThan(config()->integer($health.'database_connections.warning'))
                    ->failWhenMoreConnectionsThan(config()->integer($health.'database_connections.failure'))
                : null,
            CacheCheck::new(),
            config()->boolean($health.'disk_space.enabled')
                ? UsedDiskSpaceCheck::new()
                    ->warnWhenUsedSpaceIsAbovePercentage(config()->integer($health.'disk_space.warning_percentage'))
                    ->failWhenUsedSpaceIsAbovePercentage(config()->integer($health.'disk_space.failure_percentage'))
                : null,
            QueueCheck::new()->failWhenHealthJobTakesLongerThanMinutes(config()->integer($health.'queue_heartbeat_minutes')),
            ScheduleCheck::new()->heartbeatMaxAgeInMinutes(config()->integer($health.'schedule_heartbeat_minutes')),
            DebugModeCheck::new(),
            EnvironmentCheck::new()->expectEnvironment(config()->string($health.'environment')),
            OptimizedAppCheck::new(),
            config()->boolean('core.monitor.backup.schedule') ? BackupsCheck::new() : null,
        ]));
    }
}
