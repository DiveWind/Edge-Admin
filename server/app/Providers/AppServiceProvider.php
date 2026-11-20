<?php

namespace App\Providers;

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

/**
 * 全项目的全局默认（设计文档 §4.5、§9、§10）。入口的配置在各自的 ServiceProvider（app/Http/<Name>）中。
 * 富文本净化的规则在 App\Support\Html\HtmlSanitizers（档位见 config('core.html')）。
 */
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
        $this->configureModels();
        $this->configureDatabase();
        $this->configureValidation();
        $this->configureDevCommands();
    }

    /**
     * 模型：
     * - 多态类型必须登记别名（§4.5）：通知、日志、token、角色等关联表存别名而非完整类名，以后移动类不必迁移数据。
     *   这里只开启强制，别名由各模块在自己的 Provider 中登记（含 System 的 `user`）；
     * - 非生产环境开启严格模式：禁止延迟加载（N+1）、禁止静默丢弃未声明的属性、禁止访问不存在的属性；
     * - 日期使用不可变的 CarbonImmutable；
     * - 工厂 → 模型的推断与模型 → 工厂对称：Database\Factories\Core\System\Models\UserFactory → App\Core\System\Models\User
     *   （框架默认只在 App\Models 下查找，§4.5）。
     */
    protected function configureModels(): void
    {
        Relation::requireMorphMap();

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
