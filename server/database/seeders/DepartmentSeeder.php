<?php

namespace Database\Seeders;

use App\Enums\Status;
use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * 三层部门树：总公司 → 中心 / 部门 → 小组 / 区域。
     */
    public function run(): void
    {
        $tree = [
            '总公司' => [
                '研发中心' => ['前端组', '后端组', '测试组'],
                '市场部' => ['华东区', '华南区'],
                '财务部' => [],
                '人事部' => [],
            ],
        ];

        $this->createTree($tree, null);
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     */
    protected function createTree(array $nodes, ?int $parentId): void
    {
        $sort = 0;

        foreach ($nodes as $key => $value) {
            $name = is_string($key) ? $key : $value;

            $department = Department::query()->create([
                'parent_id' => $parentId,
                'name' => $name,
                'sort' => $sort++,
                'status' => Status::Active,
            ]);

            if (is_array($value)) {
                $this->createTree($value, $department->id);
            }
        }
    }
}
