<?php

namespace Database\Seeders;

use App\Models\{Article, ArticleCategory, User};
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    /**
     * 24 篇文章分布在不同作者 / 部门，便于演示数据权限。
     */
    public function run(): void
    {
        $categories = ArticleCategory::query()->pluck('id');
        $authors = User::query()->whereNotNull('department_id')->where('username', '!=', 'disabled')->get();

        foreach (range(1, 24) as $ignored) {
            Article::factory()->by($authors->random())->create([
                'category_id' => $categories->random(),
            ]);
        }
    }
}
