<?php

namespace App\Core\System\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Console\Command;

/**
 * core:sync：依次执行模块声明的全部同步（设计文档 §11.4）——菜单与权限节点（core:sync-permissions）、字典（core:sync-dicts）、
 * 系统参数（core:sync-settings）。任何一个失败即以它的退出码结束，后面的不再执行。由 DatabaseSeeder 调用，部署时执行。
 *
 * 三个单独的命令保留，规则见各自的 Action。不包含 schedule-monitor:sync：它属于 Monitor 使用的包（System 不依赖 Monitor），
 * 同步的是调度定义而不是模块的声明，并且会删除调度中已不存在的任务，部署流程中单独执行。
 */
#[Signature('core:sync')]
#[Description('依次同步各模块声明的菜单与权限节点、字典、系统参数（任何一个失败即停止）')]
class SyncCommand extends Command
{
    /**
     * 按顺序执行的同步命令：菜单与权限在前（菜单管理、角色授权依赖它）。
     *
     * @var list<string>
     */
    public const array COMMANDS = ['core:sync-permissions', 'core:sync-dicts', 'core:sync-settings'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        foreach (self::COMMANDS as $command) {
            $this->components->twoColumnDetail("<fg=cyan;options=bold>{$command}</>");

            $code = $this->call($command);

            if ($code !== self::SUCCESS) {
                $this->components->error("{$command} 失败（退出码 {$code}），后面的同步没有执行。");

                return $code;
            }
        }

        return self::SUCCESS;
    }
}
