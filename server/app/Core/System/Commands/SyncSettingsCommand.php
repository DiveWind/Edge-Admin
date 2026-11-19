<?php

namespace App\Core\System\Commands;

use App\Core\System\Actions\SyncSettings;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Console\Command;

/**
 * core:sync-settings：把各模块在 SettingRegistry 中声明的系统参数同步到 settings 表（设计文档 §6.7）。
 * 由 core:sync 依次调用（DatabaseSeeder 与部署时在 `php artisan optimize` 之后执行，§11.4），也可单独执行。规则见 SyncSettings。
 */
#[Signature('core:sync-settings')]
#[Description('同步各模块声明的系统参数（幂等：新增、修正结构性字段，不覆盖管理员改过的值，不删除任何参数）')]
class SyncSettingsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncSettings $sync): int
    {
        $result = $sync->handle();

        if (array_sum(array_map(count(...), $result)) === 0) {
            $this->components->info('系统参数已是最新，没有变更');

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
        $this->section('收编（已标记为内置）', $result['adopted']);

        if ($result['reset'] !== []) {
            $this->components->warn('以下参数的类型已改变，库中的值不再符合，已恢复默认值：');
            $this->components->bulletList($result['reset']);
        }

        if ($result['released'] !== []) {
            $this->components->warn('以下参数已不在声明中，已转为自定义（保留值，代码不再读取，接口中不再出现）：');
            $this->components->bulletList($result['released']);
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
