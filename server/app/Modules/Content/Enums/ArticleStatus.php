<?php

namespace App\Modules\Content\Enums;

use App\Support\Enums\{HasColor, HasLabel};

/**
 * 文章的状态（设计文档 §6.11）：草稿 ⇄ 已发布。
 *
 * - draft：新增的文章；可以发布；
 * - published：已发布（published_at 为发布时间）；可以取消发布，回到草稿。
 *
 * 两种状态都可以修改、删除。实现 HasLabel、HasColor：在 ContentServiceProvider 中登记为 `content.article_status`（`/options/enums`，§6.7）。
 */
enum ArticleStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    /**
     * 显示名称（多语言，语言包 content::article）。
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('content::article.status.draft'),
            self::Published => __('content::article.status.published'),
        };
    }

    /**
     * 语义色（antd Tag）：草稿 default，已发布 success。
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'default',
            self::Published => 'success',
        };
    }
}
