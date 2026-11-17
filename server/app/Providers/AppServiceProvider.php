<?php

namespace App\Providers;

use App\Jobs\{RunScheduledTask, SendAnnouncementNotificationChunk, SendAnnouncementNotifications};
use App\Models\{Permission, PersonalAccessToken, User};
use Carbon\{CarbonImmutable, CarbonInterval};
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\{OpenApi, SecurityScheme};
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Date, Gate, Queue, RateLimiter, URL};
use Illuminate\Support\{Number, ServiceProvider};
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
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
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureQueues();
        $this->configureApiDocs();
        $this->configureAboutCommand();
        $this->configureDevCommands();
    }

    /**
     * 框架级默认值：
     * - 非生产环境开启 Eloquent 严格模式（禁止懒加载、静默丢弃未 fillable 的属性、访问不存在的属性）；
     * - 日期统一为不可变的 CarbonImmutable，避免链式修改误改原值；
     * - 生产环境禁止 migrate:fresh / db:wipe 等破坏性命令；
     * - 密码规则：生产环境至少 8 位且包含字母与数字，其他环境至少 8 位；
     * - 按配置强制 https 链接（TLS 在反向代理终止时）；
     * - Sanctum 使用自定义 PersonalAccessToken（记录 ip / user_agent，用于在线会话）；
     * - 全部 Form Request 拒绝未声明的字段（422 `errors.<字段>`），请求体必须与契约一致；
     *   个别字段值本身是任意 JSON 的请求（系统参数）用 #[FailOnUnknownFields(false)] 退出。
     */
    protected function configureDefaults(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands($this->app->isProduction());

        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()
            : Password::min(8));

        URL::forceHttps(config()->boolean('edge.force_https'));

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        FormRequest::failOnUnknownFields();
    }

    /**
     * 功能权限：权限码（形如 `模块.资源.操作`，必含 "."）即 Gate ability，拥有则放行；
     * Policy 方法名（view / update / delete…）不含 "."，返回 null 交给 Policy 判定，
     * 因此超级管理员同样受记录级规则约束（如不能删除自己）。
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }

    /**
     * api：全部 /api 接口（bootstrap/app.php 中 throttleApi()），已登录按用户、未登录按 IP 计数。
     * 登录接口另按"用户名 + IP"只累计失败次数，成功后清零（见 LoginRequest::authenticate()）。
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(config()->integer('edge.rate_limit.api_per_minute'))
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }

    /**
     * 队列任务的集中路由（Laravel 13 Queue::route()）：Job 类上不声明队列，部署时用 EDGE_QUEUE_* 拆分，
     * 留空（null）为连接的默认队列。见 config('edge.queues')。
     */
    protected function configureQueues(): void
    {
        Queue::route([
            SendAnnouncementNotifications::class => config('edge.queues.notifications'),
            SendAnnouncementNotificationChunk::class => config('edge.queues.notifications'),
            RunScheduledTask::class => config('edge.queues.tasks'),
        ]);
    }

    /**
     * Scramble 生成的 OpenAPI 文档（/docs/api），默认仅 local 环境可访问。
     */
    protected function configureApiDocs(): void
    {
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer'));
        });
    }

    /**
     * `php artisan about` 中的 Edge Admin 区块（值为闭包，仅在执行 about 时求值）。
     */
    protected function configureAboutCommand(): void
    {
        AboutCommand::add('Edge Admin', fn (): array => [
            'Version' => config()->string('edge.version'),
            'Locales' => implode(', ', config()->array('edge.locales')),
            'Token TTL' => CarbonInterval::minutes(config()->integer('edge.auth.token_ttl'))->cascade()->forHumans(short: true),
            'Upload Disk' => config()->string('edge.uploads.disk'),
            'Upload Max Size' => Number::fileSize(config()->integer('edge.uploads.max_size') * 1024),
            'Queues' => collect(config()->array('edge.queues'))
                ->map(fn (mixed $queue, string $purpose): string => "{$purpose}: ".(is_string($queue) && $queue !== '' ? $queue : 'default'))
                ->implode(', '),
            'Permissions' => fn (): string => rescue(fn (): string => Number::format(Permission::query()->count()), '-', false),
        ]);
    }

    /**
     * `php artisan dev`（composer dev）额外启动调度器，便于本地观察定时任务。
     */
    protected function configureDevCommands(): void
    {
        DevCommands::artisan('schedule:work', 'schedule');
    }
}
