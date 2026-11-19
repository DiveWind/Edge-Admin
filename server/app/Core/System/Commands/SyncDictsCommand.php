<?php

namespace App\Core\System\Commands;

use App\Core\System\Actions\SyncDicts;
use Illuminate\Console\Attributes\{Description, Signature};
use Illuminate\Console\Command;

/**
 * core:sync-dicts：把各模块在 DictRegistry 中声明的字典类型与初始字典项同步到数据库（设计文档 §6.7）。
 * 由 core:sync 依次调用（DatabaseSeeder 与部署时在 `php artisan optimize` 之后执行，§11.4），也可单独执行。规则见 SyncDicts。
 */
#[Signature('core:sync-dicts')]
#[Description('同步各模块声明的字典类型与初始字典项（幂等：新增、收编同名的自定义类型与字典项，不覆盖管理员的调整，不删除任何数据）')]
class SyncDictsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SyncDicts $sync): int
    {
        $result = $sync->handle();

        if (array_sum(array_map(count(...), $result)) === 0) {
            $this->components->info('字典已是最新，没有变更');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '同步完成：类型新增 %d 个、收编 %d 个、转为自定义 %d 个；字典项新增 %d 个、收编 %d 个、转为自定义 %d 个',
            count($result['created']),
            count($result['adopted']),
            count($result['released']),
            count($result['created_items']),
            count($result['adopted_items']),
            count($result['released_items']),
        ));

        $this->section('新增类型', $result['created']);
        $this->section('收编同 code 的自定义类型（已标记为内置）', $result['adopted']);
        $this->section('新增字典项', $result['created_items']);
        $this->section('收编同 value 的自定义字典项（已标记为内置）', $result['adopted_items']);

        if ($result['released'] !== [] || $result['released_items'] !== []) {
            $this->components->warn('以下内置类型、字典项已不在声明中，已转为自定义（保留数据），不需要时请在字典管理中删除：');
            $this->components->bulletList([...$result['released'], ...$result['released_items']]);
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
