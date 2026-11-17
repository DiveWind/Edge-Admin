<?php

namespace Database\Seeders;

use App\Enums\AnnouncementType;
use App\Jobs\SendAnnouncementNotifications;
use App\Models\{Announcement, User};
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $published = [
            ['title' => '欢迎使用 Edge Admin', 'content' => 'Edge Admin 是一个基于 Laravel 与 Ant Design Pro 的 RBAC 中后台脚手架。', 'type' => AnnouncementType::Announcement],
            ['title' => '系统维护通知', 'content' => '本周六 22:00 - 23:00 进行例行维护，期间可能无法访问。', 'type' => AnnouncementType::Notice],
        ];

        foreach ($published as $attributes) {
            $announcement = Announcement::factory()->published()->create([...$attributes, 'created_by' => $admin->id]);

            SendAnnouncementNotifications::dispatchSync($announcement);
        }

        Announcement::factory()->create([
            'title' => '新版本发布计划（草稿）',
            'content' => '下个版本将支持更多业务模块示例。',
            'type' => AnnouncementType::Notice,
            'created_by' => $admin->id,
        ]);
    }
}
