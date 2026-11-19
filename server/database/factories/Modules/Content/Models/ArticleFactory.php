<?php

namespace Database\Factories\Modules\Content\Models;

use App\Core\System\Models\User;
use App\Modules\Content\Enums\ArticleStatus;
use App\Modules\Content\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 文章：默认是分类为 news 的草稿（分类是字典 content.article_category 的 value，工厂不检查字典）。
 *
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'category' => 'news',
            'status' => ArticleStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    /**
     * 已发布。
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * 已删除（软删除）。
     */
    public function trashed(): static
    {
        return $this->state(fn (): array => ['deleted_at' => now()]);
    }
}
