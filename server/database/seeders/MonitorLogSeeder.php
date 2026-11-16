<?php

namespace Database\Seeders;

use App\Models\LoginLog;
use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 近 14 天的登录 / 操作日志，便于工作台图表展示。
 */
class MonitorLogSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get();

        foreach (range(1, 120) as $ignored) {
            $user = $users->random();

            LoginLog::factory()->create(['user_id' => $user->id, 'username' => $user->username]);
        }

        foreach (range(1, 60) as $ignored) {
            OperationLog::factory()->create(['user_id' => $users->random()->id]);
        }
    }
}
