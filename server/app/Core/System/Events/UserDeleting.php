<?php

namespace App\Core\System\Events;

use App\Core\System\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 用户即将被删除（Core 事件，设计文档 §5.5）：DeleteUser 在事务中、删除用户行之前派发（此时用户与其关联数据仍在）。
 *
 * 监听方在**同一事务中同步**清理本模块属于该用户的数据（任何一步失败都整体回滚，删除不会成功一半）：
 * System 删除他发起的导出（连同文件），Message 删除他的通知。不要在监听中做耗时或对外的操作——那些监听 UserDeleted。
 */
class UserDeleting
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
