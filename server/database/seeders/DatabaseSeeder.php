<?php

namespace Database\Seeders;

use App\Core\System\Models\{Role, User};
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * 先同步各模块声明的菜单与权限节点，并确保超管角色存在（core:sync-permissions，各环境都执行，设计文档 §8.2）；
     * 再把调度定义同步到定时任务监控（schedule-monitor:sync，§6.6），后台的定时任务页才有数据。部署时两者同样执行（§11.4）。
     *
     * 演示账号的密码都是 password，只在本地与测试环境创建（php artisan migrate:fresh --seed）：
     * - admin：启用的管理员，拥有超管角色；
     * - disabled：停用的账号，登录时提示账号已停用。
     */
    public function run(): void
    {
        foreach (['core:sync-permissions', 'schedule-monitor:sync'] as $command) {
            if ($this->command === null) {
                Artisan::call($command);
            } else {
                $this->command->call($command);
            }
        }

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::factory()->create([
            'username' => 'admin',
            'name' => '管理员',
            'email' => 'admin@example.com',
        ])->assignRole(Role::SUPER_ADMIN);

        User::factory()->disabled()->create([
            'username' => 'disabled',
            'name' => '已停用的账号',
            'email' => 'disabled@example.com',
        ]);
    }
}
