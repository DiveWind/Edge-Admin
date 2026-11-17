<?php

use Illuminate\Support\Facades\Facade;

/*
|--------------------------------------------------------------------------
| Architecture
|--------------------------------------------------------------------------
|
| 把分层约定固化为测试（规则说明见 docs/backend-guide.md）。
| 未使用 strict 预设：它要求 declare(strict_types=1) 与 final 类，与 Laravel / Pint 的 laravel 预设冲突。
|
*/

arch('php')->preset()->php();

arch('security')->preset()->security();

/*
 * laravel 预设包含：控制器只允许资源方法与 __invoke、Commands 以 Command 结尾、
 * Models 继承 Model、Enums 必须是 enum、App\Http 只在 Http / Providers 中使用、禁用 dd / dump / env / exit / ray 等。
 */
arch('laravel')->preset()->laravel();

arch('controllers only orchestrate: authorize declaratively (#[Authorize]), call an action or simple Eloquent, return a resource')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Support\Facades\Artisan',
        'Illuminate\Support\Facades\Storage',
        'Illuminate\Support\Facades\Cache',
        'Illuminate\Support\Facades\Bus',
        'Illuminate\Support\Facades\Queue',
        'Illuminate\Support\Facades\Notification',
        'Illuminate\Support\Facades\Gate',
        'App\Jobs',
        'App\Notifications',
    ]);

arch('controllers depend only on the allowed layers')
    ->expect('App\Http\Controllers')
    ->toOnlyUse([
        'App\Actions',
        'App\Enums',
        'App\Http\Controllers',
        'App\Http\Requests',
        'App\Http\Resources',
        'App\Models',
        'App\Services',
        'Illuminate\Container\Attributes\CurrentUser',
        'Illuminate\Http',
        'Illuminate\Queue\Failed\FailedJobProviderInterface',
        'Illuminate\Routing\Attributes\Controllers',
        'abort',
        'response',
        '__',
        'now',
    ])
    // 分组导入 `use A\{B, C}` 的条目名会被解析为独立的裸类名；与门面别名（Response、Request…）同名时属于误报。
    ->ignoring(array_keys(Facade::defaultAliases()->all()));

arch('actions are invoked through a single handle() method and stay out of the HTTP layer')
    ->expect('App\Actions')
    ->toBeClasses()
    ->toHaveMethod('handle')
    ->not->toUse(['App\Http', 'Illuminate\Http\Request', 'Illuminate\Foundation\Http\FormRequest', 'Illuminate\Http\Resources']);

arch('services and models stay out of the HTTP layer')
    ->expect(['App\Services', 'App\Models', 'App\Rules', 'App\Casts', 'App\Enums'])
    ->not->toUse(['App\Http', 'App\Actions']);

arch('form requests')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('api resources')
    ->expect('App\Http\Resources')
    ->toExtend('Illuminate\Http\Resources\Json\JsonResource');

arch('policies only contain record level rules')
    ->expect('App\Policies')
    ->toBeClasses()
    ->not->toUse(['Illuminate\Support\Facades\DB', 'App\Http']);

arch('enums')
    ->expect('App\Enums')
    ->toBeEnums()
    ->toBeStringBackedEnums();

arch('models')
    ->expect('App\Models')
    ->classes()
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('models convert datetime input to the app timezone before storing it')
    ->expect('App\Models')
    ->classes()
    ->toUseTrait('App\Models\Concerns\StoresDatesInAppTimezone');

arch('validation rules')
    ->expect('App\Rules')
    ->toImplement('Illuminate\Contracts\Validation\ValidationRule');
