<?php

namespace Database\Factories;

use App\Models\OperationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationLog>
 */
class OperationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$method, $path, $routeName, $summary] = fake()->randomElement([
            ['POST', '/api/articles', 'articles.store', '新增文章'],
            ['PUT', '/api/articles/1', 'articles.update', '编辑文章'],
            ['PUT', '/api/users/2', 'users.update', '编辑用户'],
            ['POST', '/api/announcements', 'announcements.store', '新增公告'],
            ['PUT', '/api/auth/profile', 'auth.profile', '修改个人资料'],
            ['DELETE', '/api/articles/3', 'articles.destroy', '删除文章'],
        ]);

        return [
            'user_id' => null,
            'method' => $method,
            'path' => $path,
            'route_name' => $routeName,
            'summary' => $summary,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'status_code' => fake()->randomElement([200, 200, 200, 201, 204, 422]),
            'duration_ms' => fake()->numberBetween(5, 300),
            'payload' => [],
            'created_at' => fake()->dateTimeBetween('-13 days'),
        ];
    }
}
