<?php

namespace App\Core\System\Exporters;

use App\Core\System\Contracts\Exporter;
use App\Core\System\Models\User;
use App\Core\System\Queries\UserList;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * 用户列表的导出（对外名称 `user`，POST /system/users/export，设计文档 §6.5、§6.10）。
 *
 * 查询就是用户列表的 UserList：同一份筛选与排序白名单、同一个数据范围（visibleTo），导出的用户不会比列表多。
 * 列：id、username、name、email、status（显示名）、roles（角色的显示名，按 sort、id，逗号分隔）、created_at（应用时区）。
 *
 * @implements Exporter<User>
 */
class UserExporter implements Exporter
{
    public function __construct(private readonly UserList $list) {}

    /**
     * 导出的显示名。
     */
    public function title(): string
    {
        return 'Users';
    }

    /**
     * 导出的权限码。
     */
    public function permission(): string
    {
        return 'system.user.export';
    }

    /**
     * 列与表头。
     *
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'id' => 'ID',
            'username' => 'Username',
            'name' => 'Name',
            'email' => 'Email',
            'status' => 'Status',
            'roles' => 'Roles',
            'created_at' => 'Created at',
        ];
    }

    /**
     * 用户列表的查询。
     *
     * @param  array<string, mixed>  $parameters
     * @return Builder<User>
     */
    public function query(User $actor, array $parameters): Builder
    {
        return $this->list->builder($actor, $parameters)->getEloquentBuilder();
    }

    /**
     * 一行。
     *
     * @return array<string, string|int|null>
     */
    public function map(Model $row): array
    {
        return [
            'id' => $row->id,
            'username' => $row->username,
            'name' => $row->name,
            'email' => $row->email,
            'status' => $row->status->label(),
            'roles' => $row->roles->sortBy([['sort', 'asc'], ['id', 'asc']])->pluck('title')->implode(', '),
            'created_at' => $row->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
