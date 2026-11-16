<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleCategory>
 */
class ArticleCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'sort' => fake()->numberBetween(0, 100),
            'status' => Status::Active,
        ];
    }
}
