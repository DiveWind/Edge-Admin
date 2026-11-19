<?php

namespace App\Http\Admin\Content\Resources;

use App\Http\Admin\System\Resources\AttachmentResource;
use App\Modules\Content\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * 文章的输出格式（Admin 入口，设计文档 §6.11）。
 *
 * 逐个列出字段，禁止整模型 toArray() 输出；日期字段输出 toIso8601String()（设计文档 §9、§10.1）。
 *
 * - category：字典 content.article_category 的 value（前端用 /options/dicts 显示名称）；
 * - cover：封面附件（格式同 AttachmentResource，签名地址由本接口签发：文章已通过它自己的 view 授权，§6.8），没有封面时为 null；
 * - creator：创建人 `{ id, username, name }`（已删除时为 null）；deleted_at：已删除的时间（未删除为 null，§10.1）；
 * - content（净化后的 HTML）只在 withDetails() 时输出（详情与新增、修改、发布、取消发布、恢复的响应），列表不含。
 *
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /**
     * 是否输出 content。
     */
    private bool $details = false;

    /**
     * 输出 content（详情）。
     */
    public function withDetails(): static
    {
        $this->details = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->details ? $this->content : new MissingValue,
            'category' => $this->category,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'cover' => $this->cover === null ? null : new AttachmentResource($this->cover),
            'created_by' => $this->created_by,
            'creator' => $this->creator?->only(['id', 'username', 'name']),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * 输出前需要预加载的关联（列表由 ArticleList 预加载同样的关联）。
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['creator', 'cover'];
    }
}
