<?php

namespace Database\Seeders;

use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;

class ArticleCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['技术', '产品', '市场', '公司动态'] as $sort => $name) {
            ArticleCategory::factory()->create(['name' => $name, 'sort' => $sort]);
        }
    }
}
