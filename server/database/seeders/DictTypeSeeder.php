<?php

namespace Database\Seeders;

use App\Models\DictType;
use Illuminate\Database\Seeder;

/**
 * 预置数据字典：文章来源（文章模块使用）与两个通用字典。
 */
class DictTypeSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, remark: string, items: list<array{0: string, 1: string, 2: string|null}>}>
     */
    protected const array DICTS = [
        'article_source' => [
            'name' => '文章来源',
            'remark' => '文章模块 source 字段',
            'items' => [['原创', 'original', 'green'], ['转载', 'reprint', 'blue'], ['翻译', 'translation', 'purple']],
        ],
        'common_yes_no' => [
            'name' => '是否',
            'remark' => '通用是 / 否',
            'items' => [['是', 'yes', 'green'], ['否', 'no', 'default']],
        ],
        'user_gender' => [
            'name' => '用户性别',
            'remark' => '通用性别',
            'items' => [['男', 'male', null], ['女', 'female', null], ['未知', 'unknown', null]],
        ],
    ];

    public function run(): void
    {
        foreach (self::DICTS as $code => $dict) {
            $type = DictType::factory()->create(['name' => $dict['name'], 'code' => $code, 'remark' => $dict['remark']]);

            foreach ($dict['items'] as $sort => [$label, $value, $color]) {
                $type->items()->create(['label' => $label, 'value' => $value, 'color' => $color, 'sort' => $sort, 'status' => 'active']);
            }
        }
    }
}
