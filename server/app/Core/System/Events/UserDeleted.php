<?php

namespace App\Core\System\Events;

use App\Core\System\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 用户已被删除（Core 事件，设计文档 §5.5）：DeleteUser 派发，在删除用户的事务提交之后才分发给监听方（ShouldDispatchAfterCommit），
 * 事务回滚时不分发。$user 是删除前的模型（exists 为 false，只能读取它的属性，不能再查询关联）。
 *
 * 用于删除之后才能做、且不必与删除同进退的事：通知外部系统、清理缓存、派发队列任务等。要与删除在同一事务中清理的数据监听 UserDeleting。
 */
class UserDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
