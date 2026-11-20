<?php

namespace App\Modules\Content;

use App\Core\System\Services\{DictRegistry, MenuRegistry};
use App\Core\System\Services\{EnumRegistry, ExporterRegistry};
use App\Modules\Content\Models\Article;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Content（业务模块，业务对接示范，可整块删除）：职责与对外提供的能力见同目录的 README.md。
 *
 * 注册本模块的契约绑定、多态别名、命令、事件监听、语言包与视图，并通过 Core 的扩展点接入（设计文档 §4.3、§5.5）。
 * 多态别名与契约绑定由 core:make-model、core:make-contract 自动登记。
 *
 * 只通过扩展点接入，不修改 Core 的任何文件（§5.3）：
 * - 多态别名 `content.article`（§4.5）；语言包（命名空间 `content::`，resources/lang）；
 * - 节点（resources/menus.php）→ MenuRegistry；字典类型 `content.article_category`（resources/dicts.php）→ DictRegistry；
 * - 文章状态枚举 `content.article_status`（resources/enums.php）→ EnumRegistry（/options/enums）；
 *   文章列表的导出 `content.article`（resources/exporters.php）→ ExporterRegistry。
 */
class ContentServiceProvider extends ServiceProvider
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
    public function boot(MenuRegistry $menus, DictRegistry $dicts, EnumRegistry $enums, ExporterRegistry $exporters): void
    {
        Relation::morphMap([
            'content.article' => Article::class,
        ]);

        $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'content');

        $menus->register(require __DIR__.'/resources/menus.php');
        $dicts->register(require __DIR__.'/resources/dicts.php');
        $enums->register(require __DIR__.'/resources/enums.php');
        $exporters->register(require __DIR__.'/resources/exporters.php');
    }
}
