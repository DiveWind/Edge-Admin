<?php

namespace App\Http\Admin\Monitor\Resources;

use App\Core\Monitor\Enums\LoginEvent;
use App\Core\Monitor\Models\LoginLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 登录日志的输出格式（Admin 入口，设计文档 §6.6）。
 *
 * - event：login、logout、failed、lockout、password_reset（“结果”即 event）；reason 只有 failed 有（invalid_credentials、disabled、two_factor）；
 * - via_remember 只有 login 有（是否由“记住我”的 Cookie 自动恢复登录），其余为 null；
 * - user_id 为事件涉及的账号（用户名不存在时为 null）；username 成功时为账号的用户名，失败时为提交的用户名；
 * - 时间为带时区偏移的 ISO 8601。
 *
 * @mixin LoginLog
 */
class LoginLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->loginEvent(),
            'reason' => $this->failureReason(),
            'via_remember' => $this->loginEvent() === LoginEvent::Login ? $this->getProperty('via_remember') === true : null,
            'user_id' => $this->causer_id,
            'username' => $this->stringProperty('username'),
            'ip_address' => $this->stringProperty('ip_address'),
            'user_agent' => $this->stringProperty('user_agent'),
            'request_id' => $this->stringProperty('request_id'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
