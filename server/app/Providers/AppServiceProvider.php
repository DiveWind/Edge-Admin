<?php

namespace App\Providers;

use App\Core\System\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\{DB, Date};
use Illuminate\Support\{ServiceProvider, Str};
use Illuminate\Validation\Rules\Password;
use LogicException;
use Symfony\Component\HtmlSanitizer\{HtmlSanitizer, HtmlSanitizerConfig, HtmlSanitizerInterface};

/**
 * 全项目的全局默认（设计文档 §4.5、§9、§10）。入口的配置在各自的 ServiceProvider（app/Http/<Name>）中。
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerHtmlSanitizer();
    }

    /**
     * 富文本净化（§9、§13）：模型的 SanitizedHtml 转换在写入属性时使用。HtmlSanitizer 构造后只读（配置不可变），作为单例不保存请求数据。
     *
     * - allowSafeElements()：W3C 的安全元素与属性（去掉 script、style、iframe、form、on* 事件属性等）；
     * - 链接只允许 http、https、mailto，图片等媒体只允许 http、https（不允许 javascript:、data:）；a 一律加 rel="noopener noreferrer"；
     * - 输入最长 1,000,000 字节（包的默认 20000 字节，超出部分会被静默截掉；字段的长度上限由 Form Request 校验）。
     *
     * 需要不同的规则时，在项目中重新绑定 HtmlSanitizerInterface。
     */
    protected function registerHtmlSanitizer(): void
    {
        $this->app->singleton(HtmlSanitizerInterface::class, fn (): HtmlSanitizer => new HtmlSanitizer(
            new HtmlSanitizerConfig()
                ->allowSafeElements()
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowMediaSchemes(['http', 'https'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(1_000_000),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureDatabase();
        $this->configureValidation();
        $this->configureDevCommands();
    }

    /**
     * 模型：
     * - 多态类型必须登记别名（§4.5）：通知、日志、token、角色等关联表存别名而非完整类名，以后移动类不必迁移数据；
     * - 非生产环境开启严格模式：禁止延迟加载（N+1）、禁止静默丢弃未声明的属性、禁止访问不存在的属性；
     * - 日期使用不可变的 CarbonImmutable；
     * - 工厂 → 模型的推断与模型 → 工厂对称：Database\Factories\Core\System\Models\UserFactory → App\Core\System\Models\User
     *   （框架默认只在 App\Models 下查找，§4.5）。
     */
    protected function configureModels(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
        ]);

        Factory::guessModelNamesUsing(function (Factory $factory): string {
            $model = 'App\\'.Str::of($factory::class)->after('Database\\Factories\\')->replaceLast('Factory', '');

            if (! is_subclass_of($model, Model::class)) {
                throw new LogicException('Unable to guess the model for ['.$factory::class."]: [{$model}] is not an Eloquent model.");
            }

            return $model;
        });

        Model::shouldBeStrict(! $this->app->isProduction());

        Date::use(CarbonImmutable::class);
    }

    /**
     * 生产环境禁止 db:wipe、migrate:fresh 等破坏性命令。
     */
    protected function configureDatabase(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    /**
     * 输入（§9）：
     * - 所有 Form Request 拒绝未声明的字段（只检查请求体，不检查查询字符串）；
     * - 密码规则 `Password::defaults()`：生产环境至少 12 位，含大小写字母、数字与符号。
     */
    protected function configureValidation(): void
    {
        FormRequest::failOnUnknownFields();

        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->letters()->numbers()->symbols()
            : Password::min(8));
    }

    /**
     * `composer dev`（php artisan dev）：在默认的 serve、queue:listen、pail 之外，同时运行调度器。
     */
    protected function configureDevCommands(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        DevCommands::artisan('schedule:work', 'schedule');
    }
}
