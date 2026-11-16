<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
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
        $status = fake()->randomElement(PublishStatus::cases());

        return [
            'category_id' => ArticleCategory::factory(),
            'title' => fake()->realText(20),
            'summary' => fake()->realText(60),
            'cover' => null,
            'content' => fake()->realText(600),
            'status' => $status,
            'published_at' => $status === PublishStatus::Published ? fake()->dateTimeBetween('-30 days') : null,
            'department_id' => null,
            'created_by' => null,
        ];
    }

    /**
     * 指定作者，并以作者部门作为文章部门。
     */
    public function by(User $author): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_by' => $author->id,
            'department_id' => $author->department_id,
        ]);
    }
}
