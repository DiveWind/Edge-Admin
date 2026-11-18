<?php

namespace Database\Seeders;

use App\Core\System\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * 演示账号的密码都是 password，只在本地与测试环境创建（php artisan migrate:fresh --seed）：
     * - admin：启用的管理员；
     * - disabled：停用的账号，登录时提示账号已停用。
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::factory()->create([
            'username' => 'admin',
            'name' => '管理员',
            'email' => 'admin@example.com',
        ]);

        User::factory()->disabled()->create([
            'username' => 'disabled',
            'name' => '已停用的账号',
            'email' => 'disabled@example.com',
        ]);
    }
}
