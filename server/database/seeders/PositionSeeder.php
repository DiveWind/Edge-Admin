<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['name' => 'CEO', 'code' => 'ceo'],
            ['name' => '技术总监', 'code' => 'cto'],
            ['name' => '项目经理', 'code' => 'pm'],
            ['name' => '开发工程师', 'code' => 'developer'],
            ['name' => '测试工程师', 'code' => 'qa'],
            ['name' => '市场专员', 'code' => 'marketing'],
            ['name' => '普通员工', 'code' => 'staff'],
        ];

        foreach ($positions as $sort => $position) {
            Position::factory()->create([...$position, 'sort' => $sort]);
        }
    }
}
