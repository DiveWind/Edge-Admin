<?php

namespace App\Core\Monitor;

use App\Core\Monitor\Listeners\RecordLoginActivity;
use App\Core\Monitor\Models\{Activity, LoginLog, OperationLog, Session};
use App\Core\Monitor\Policies\PersonalAccessTokenPolicy;
use App\Core\System\Contracts\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\{Event, Gate};
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Monitor（内核模块）：职责与对外提供的能力见同目录的 README.md。
 *
 * 注册本模块的契约绑定、多态别名、命令、事件监听、语言包与视图，并通过 Core 的扩展点接入（设计文档 §4.3、§5.5）。
 * 多态别名与契约绑定由 core:make-model、core:make-contract 自动登记。
 *
 * - 菜单与权限节点（resources/menus.php）注册到 System 的 MenuRegistry；
 * - 登录日志：RecordLoginActivity 订阅认证事件（设计文档 §7.3）；
 * - Sanctum 的 PersonalAccessToken 不在 app/ 中，Policy 无法按命名推断，这里显式绑定 PersonalAccessTokenPolicy。
 *
 * 操作日志的中间件在后台入口（App\Http\Admin\Middleware\RecordOperationLog），过期日志的清理在 routes/console.php 中调度。
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
    public function boot(MenuRegistry $menus): void
    {
        Relation::morphMap([
            'activity' => Activity::class,
            'operation_log' => OperationLog::class,
            'login_log' => LoginLog::class,
            'session' => Session::class,
        ]);

        $menus->register(require __DIR__.'/resources/menus.php');

        Gate::policy(PersonalAccessToken::class, PersonalAccessTokenPolicy::class);

        Event::subscribe(RecordLoginActivity::class);
    }
}
