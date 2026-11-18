<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 当前用户信息，字段与 Vben 的 UserInfo 一致（设计文档 §6.2）：
 *
 * - userId：字符串（Vben 的类型定义）；
 * - realName：姓名；avatar：头像地址，未设置时为 null（前端使用默认头像）；
 * - roles：角色标识列表（接入角色前为空数组）；desc：描述；
 * - homePath：登录后进入的前端页面，见 config('core.apps.admin.home_path')。
 *
 * @mixin User
 */
class UserInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'userId' => (string) $this->id,
            'username' => $this->username,
            'realName' => $this->name,
            'avatar' => $this->avatar,
            'roles' => [],
            'desc' => '',
            'homePath' => config()->string('core.apps.admin.home_path'),
        ];
    }
}
