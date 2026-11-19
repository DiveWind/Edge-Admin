<?php

namespace Database\Factories\Core\System\Models;

use App\Core\System\Enums\SettingType;
use App\Core\System\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state：一个仍在声明中、没有改过值的 string 参数。
     * 测试中通常按某个已声明参数的键创建（`Setting::factory()->create(['key' => 'site.name', ...])`）。
     *
     * 列出全部字段（可空的也写上）：严格模式下访问未设置的属性会报错。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $group = fake()->unique()->regexify('[a-z]{8}');

        return [
            'key' => "{$group}.".fake()->regexify('[a-z]{6}'),
            'group' => $group,
            'type' => SettingType::String,
            'name' => fake()->words(2, true),
            'remark' => null,
            'value' => null,
            'is_public' => false,
            'is_builtin' => true,
        ];
    }
}
