<?php

namespace App\Core\Monitor\Actions;

use App\Core\Monitor\Models\Session;

/**
 * 强制下线一个会话（设计文档 §6.6、§7.3）：删除会话表中的记录，该浏览器的下一次请求即为未登录（401）。
 * 授权（当前会话、超管保护、数据范围）由 SessionPolicy 判定。
 */
class LogoutSession
{
    /**
     * Execute the action.
     */
    public function handle(Session $session): void
    {
        $session->delete();
    }
}
