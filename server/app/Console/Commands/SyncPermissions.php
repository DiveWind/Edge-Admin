<?php

namespace App\Console\Commands;

use App\Enums\PermissionType;
use App\Models\Permission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('permission:sync')]
#[Description('将 config/permissions.php 中声明的权限点同步到 permissions 表（幂等：新增 / 更新 / 删除多余项）')]
class SyncPermissions extends Command
{
    /**
     * 本次同步涉及的权限码。
     *
     * @var list<string>
     */
    protected array $syncedCodes = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var list<array<string, mixed>> $tree */
        $tree = config('permissions', []);

        $this->syncedCodes = [];

        $deleted = DB::transaction(function () use ($tree): int {
            $this->syncNodes($tree, null);

            return Permission::query()->whereNotIn('code', $this->syncedCodes)->delete();
        });

        $this->components->info(sprintf('已同步 %d 个权限点，删除 %d 个多余项。', count($this->syncedCodes), $deleted));

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    protected function syncNodes(array $nodes, ?int $parentId): void
    {
        foreach ($nodes as $index => $node) {
            $code = (string) $node['code'];
            $menu = $this->upsert($code, (string) $node['name'], PermissionType::Menu, $parentId, $index);

            /** @var array<string, string> $actions */
            $actions = $node['actions'] ?? [];

            foreach (array_keys($actions) as $actionIndex => $action) {
                $this->upsert("{$code}.{$action}", $actions[$action], PermissionType::Action, $menu->id, $actionIndex);
            }

            /** @var list<array<string, mixed>> $children */
            $children = $node['children'] ?? [];

            $this->syncNodes($children, $menu->id);
        }
    }

    protected function upsert(string $code, string $name, PermissionType $type, ?int $parentId, int $sort): Permission
    {
        $this->syncedCodes[] = $code;

        return Permission::query()->updateOrCreate(
            ['code' => $code],
            ['name' => $name, 'type' => $type, 'parent_id' => $parentId, 'sort' => $sort],
        );
    }
}
