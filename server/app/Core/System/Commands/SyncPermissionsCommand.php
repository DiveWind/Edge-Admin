<?php

namespace App\Core\System\Commands;

use App\Core\System\Actions\SyncPermissions;
use App\Core\System\Models\Role;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Console\Command;

/**
 * core:sync-permissions：把各模块在 MenuRegistry 中声明的菜单与权限节点同步到 permissions 表（设计文档 §8.2）。
 * 由 DatabaseSeeder 调用，部署时在 `php artisan optimize` 之后执行（§11.4）。规则见 SyncPermissions。
 */
#[Signature('core:sync-permissions')]
#[Description('同步各模块声明的菜单与权限节点（幂等：新增、收编同名自定义节点、修正结构性字段，不覆盖管理员的调整，不删除任何节点）')]
class SyncPermissionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncPermissions $sync): int
    {
        $result = $sync->handle();

        $changes = count($result['created']) + count($result['updated']) + count($result['adopted']) + count($result['released']);

        if ($changes === 0 && ! $result['super_admin_created']) {
            $this->components->info('菜单与权限节点已是最新，没有变更');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '同步完成：新增 %d 个，修正 %d 个，收编 %d 个，转为自定义 %d 个',
            count($result['created']),
            count($result['updated']),
            count($result['adopted']),
            count($result['released']),
        ));

        $this->section('新增', $result['created']);
        $this->section('修正结构性字段', $result['updated']);
        $this->section('收编同名的自定义节点（已标记为内置并修正结构性字段）', $result['adopted']);

        if ($result['released'] !== []) {
            $this->components->warn('以下内置节点已不在声明中，已转为自定义节点（保留角色授权），不需要时请在菜单管理中删除：');
            $this->components->bulletList($result['released']);
        }

        if ($result['super_admin_created']) {
            $this->components->info('已创建超管角色 '.Role::SUPER_ADMIN);
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $names
     */
    private function section(string $title, array $names): void
    {
        if ($names === []) {
            return;
        }

        $this->components->twoColumnDetail("<fg=green;options=bold>{$title}</>", (string) count($names));
        $this->components->bulletList($names);
    }
}
