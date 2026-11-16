<?php

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * 内置系统参数（is_system）。默认值取自 config，后端通过 setting() 读取，系统参数优先于 config。
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['site', 'site.name', '站点名称', SettingType::String, config()->string('app.name'), true, '登录页、浏览器标题展示'],
            ['site', 'site.login_notice', '登录页公告', SettingType::String, '演示环境数据每日重置，请勿录入真实信息。', true, '为空时不展示'],
            ['site', 'site.icp', '备案号', SettingType::String, '', true, '页脚展示的 ICP 备案号，为空时不展示'],
            ['security', 'security.login_max_attempts', '登录尝试次数', SettingType::Number, config()->integer('edge.auth.login_max_attempts'), false, '每分钟每个"用户名 + IP"允许的登录尝试次数'],
            ['log', 'log.retention_days', '日志保留天数', SettingType::Number, config()->integer('edge.logs.retention_days'), false, '登录 / 操作 / 任务日志保留天数，由 model:prune 每日清理'],
        ];

        foreach ($settings as [$group, $key, $name, $type, $value, $isPublic, $remark]) {
            Setting::factory()->system()->create([
                'group' => $group,
                'key' => $key,
                'name' => $name,
                'type' => $type,
                'value' => $value,
                'is_public' => $isPublic,
                'remark' => $remark,
            ]);
        }
    }
}
