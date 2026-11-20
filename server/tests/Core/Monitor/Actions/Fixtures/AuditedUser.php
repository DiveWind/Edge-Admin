<?php

namespace Tests\Core\Monitor\Actions\Fixtures;

use App\Core\Monitor\Contracts\Auditable;
use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Attributes\Table;

/**
 * 测试用的可审计模型：复用 users 表与 `user` 多态别名，name 作为“大字段”只记摘要。
 */
#[Table(name: 'users')]
class AuditedUser extends User implements Auditable
{
    public function getMorphClass(): string
    {
        return 'user';
    }

    /**
     * @return array{username: string, name: string, status: string}
     */
    public function auditSnapshot(): array
    {
        return ['username' => $this->username, 'name' => $this->name, 'status' => $this->status->value];
    }

    /**
     * @return list<string>
     */
    public function auditDigestedFields(): array
    {
        return ['name'];
    }
}
