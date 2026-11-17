<?php

namespace App\Console\Commands;

use App\Actions\System\SyncBuiltinPermissions;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Config;

#[Signature('permission:sync')]
#[Description('将 config/permissions.php 中声明的内置菜单权限同步到 permissions 表（幂等：新增缺失项、修正结构性字段，不覆盖界面上的修改，不删除任何节点）')]
class SyncPermissionsCommand extends Command
{
    /**
     * Execute the console command.
     *
     * @param  list<array<string, mixed>>  $tree  config/permissions.php 声明的菜单权限树
     */
    public function handle(SyncBuiltinPermissions $sync, #[Config('permissions')] array $tree): int
    {
        $result = $sync->handle($tree);

        $this->components->info(sprintf(
            '已同步 %d 个内置节点：新增 %d 个，修正 %d 个。',
            $result['declared'],
            $result['created'],
            $result['updated'],
        ));

        if ($result['released'] !== []) {
            $this->components->warn('以下节点已不在配置中，已转为自定义节点（保留角色授权），不需要时请在菜单管理中删除：');
            $this->components->bulletList($result['released']);
        }

        return self::SUCCESS;
    }
}
