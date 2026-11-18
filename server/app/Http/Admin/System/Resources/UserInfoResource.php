<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 当前用户信息，字段与 Vben 的 UserInfo 一致（设计文档 §6.2）：
 *
 * - userId：字符串（Vben 的类型定义）；
 * - realName：姓名；email：邮箱，可为 null；avatar：头像地址，未设置时为 null（前端使用默认头像）；
 * - twoFactorEnabled：两步验证已确认开启（以 two_factor_confirmed_at 判断；已开启但未确认时为 false）；
 * - roles：启用角色的标识（roles.name，§8.5；停用的角色不输出）；desc：描述；
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
            'email' => $this->email,
            'avatar' => $this->avatar,
            'twoFactorEnabled' => $this->two_factor_confirmed_at !== null,
            'roles' => $this->enabledRoles()->pluck('name')->values()->all(),
            'desc' => '',
            'homePath' => config()->string('core.apps.admin.home_path'),
        ];
    }
}
