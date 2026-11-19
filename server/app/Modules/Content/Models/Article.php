<?php

namespace App\Modules\Content\Models;

use App\Core\System\Models\{Attachment, User};
use App\Core\System\Models\Concerns\{HasAttachments, HasDataScope};
use App\Modules\Content\Enums\ArticleStatus;
use App\Support\Eloquent\Casts\SanitizedHtml;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Carbon\CarbonInterface;
use Database\Factories\Modules\Content\Models\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphOne};

/**
 * 文章（多态别名 `content.article`，见 ContentServiceProvider；设计文档 §6.11）。
 *
 * - content：HTML，写入属性时净化（SanitizedHtml，默认档位 rich，§9），存的、输出的都是净化后的内容；title、summary 是纯文本；
 * - category：字典 `content.article_category` 的 value（resources/dicts.php，写入前由 InDict 校验）；
 * - status：ArticleStatus（草稿 ⇄ 已发布），published_at 为发布时间（草稿为 null）；
 * - created_by：创建人（HasDataScope 的归属列，§8.4）；
 * - HasAttachments：封面是 `cover` 用途上的一个图片附件（cover()）；软删除时保留，彻底删除时连同文件删除；
 * - SoftDeletes：删除是软删除，恢复、彻底删除与“已删除”筛选是整套功能（§10.1）。
 *
 * 写入只在 Content 模块的 Action 中进行（设计文档 §5.2）；Policy 与工厂按默认规则自动推断。
 *
 * @property int $id
 * @property string $title
 * @property string|null $summary
 * @property string $content
 * @property string $category
 * @property ArticleStatus $status
 * @property CarbonInterface|null $published_at
 * @property int|null $created_by
 * @property CarbonInterface|null $deleted_at
 */
#[Fillable(['title', 'summary', 'content', 'category'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasAttachments, HasDataScope, HasFactory, SoftDeletes, UsesAppTimezone;

    /**
     * 封面附件的用途名。
     */
    public const string COVER_COLLECTION = 'cover';

    /**
     * 分类的字典类型（resources/dicts.php）。
     */
    public const string CATEGORY_DICT = 'content.article_category';

    /**
     * 摘要的最大长度（字符）。
     */
    public const int SUMMARY_MAX_LENGTH = 500;

    /**
     * 正文的最大长度（字符，按净化前计）。
     */
    public const int CONTENT_MAX_LENGTH = 65535;

    /**
     * 创建人（已删除时为 null）。
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 封面（`cover` 用途上绑定的图片附件，没有时为 null）。
     *
     * @return MorphOne<Attachment, $this>
     */
    public function cover(): MorphOne
    {
        return $this->attachmentIn(self::COVER_COLLECTION);
    }

    public function isDraft(): bool
    {
        return $this->status === ArticleStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::Published;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => SanitizedHtml::class,
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }
}
