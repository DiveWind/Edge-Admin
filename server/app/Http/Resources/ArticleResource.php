<?php

namespace App\Http\Resources;

use App\Models\{Article, ArticleCategory, Department, User};
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn (ArticleCategory $category): array => $category->only('id', 'name')),
            'title' => $this->title,
            'summary' => $this->summary,
            'cover' => $this->cover,
            'content' => $this->content,
            'status' => $this->status,
            'source' => $this->source,
            'published_at' => $this->published_at?->toIso8601String(),
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', fn (Department $department): array => $department->only('id', 'name')),
            'created_by' => $this->created_by,
            'author' => $this->whenLoaded('author', fn (User $author): array => $author->only('id', 'name')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
