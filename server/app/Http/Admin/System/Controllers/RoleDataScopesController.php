<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Contracts\DataScopeProvider;
use App\Core\System\Models\{Role, User};
use App\Core\System\Services\DataScopeRegistry;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 角色表单的数据范围选项（GET /system/roles/data-scopes，设计文档 §6.5、§8.4）：`[{ value, label }]`，按登记顺序；
 * label 随 Accept-Language 翻译。业务模块注册的范围自动出现。需要角色的新增或修改权限。
 * 只列出操作者可授予的范围（§8.5，DataScopeRegistry::grantableKeysFor()），前端不会给出必然 422 的选项。
 */
class RoleDataScopesController
{
    /**
     * Handle the incoming request.
     *
     * @return list<array{value: string, label: string}>
     */
    #[Authorize('viewFormOptions', Role::class)]
    public function __invoke(DataScopeRegistry $scopes, #[CurrentUser] User $actor): array
    {
        return array_values(array_map(
            fn (DataScopeProvider $provider): array => ['value' => $provider->key(), 'label' => $provider->label()],
            array_intersect_key($scopes->all(), array_flip($scopes->grantableKeysFor($actor))),
        ));
    }
}
