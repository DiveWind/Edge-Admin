<?php

use App\Core\System\Contracts\DataScopeProvider;
use App\Core\System\Models\{Role, User};
use App\Core\System\Services\DataScopeRegistry;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;

/*
|--------------------------------------------------------------------------
| 数据权限的合并规则（设计文档 §8.4），用 User 模型验证 visibleTo() 与 isVisibleTo() 一致
|--------------------------------------------------------------------------
*/

/**
 * 测试用的范围 `team`：只看 name 以 “Team ” 开头的用户。
 */
function registerTeamScope(): void
{
    resolve(DataScopeRegistry::class)->register([new class implements DataScopeProvider
    {
        public function key(): string
        {
            return 'team';
        }

        public function label(): string
        {
            return 'Team';
        }

        public function constrain(Builder $query, User $user, Collection $roles): void
        {
            $query->where('name', 'like', 'Team %');
        }

        public function contains(Model $record, User $user, Collection $roles): bool
        {
            return str_starts_with((string) $record->getAttribute('name'), 'Team ');
        }
    }]);
}

/**
 * 断言 $actor 可见的用户恰好是 $expected：查询范围与逐条判定的结果相同。
 *
 * @param  list<User>  $expected
 */
function expectVisibleUsers(User $actor, array $expected): void
{
    $queried = User::query()->visibleTo($actor)->orderBy('id')->pluck('id')->all();
    $checked = User::query()->orderBy('id')->get()->filter(fn (User $user): bool => $user->isVisibleTo($actor))->pluck('id')->values()->all();
    $ids = array_map(fn (User $user): int => $user->id, $expected);
    sort($ids);

    expect($queried)->toBe($ids)
        ->and($checked)->toBe($ids);
}

beforeEach(function (): void {
    $this->actor = User::factory()->create(['name' => 'Actor']);
    $this->own = User::factory()->create(['name' => 'Own', 'created_by' => $this->actor->id]);
    $this->team = User::factory()->create(['name' => 'Team member']);
    $this->other = User::factory()->create(['name' => 'Other']);
});

it('does not restrict super admins', function (): void {
    $this->actor->assignRole(Role::factory()->superAdmin()->create(['data_scope' => 'self']));

    expectVisibleUsers($this->actor, [$this->actor, $this->own, $this->team, $this->other]);
});

it('does not restrict when any enabled role uses the all scope', function (): void {
    $this->actor->assignRole(Role::factory()->create(['data_scope' => 'self']), Role::factory()->create(['data_scope' => 'all']));

    expectVisibleUsers($this->actor, [$this->actor, $this->own, $this->team, $this->other]);
});

it('limits the self scope to the user and the users they created', function (): void {
    $this->actor->assignRole(Role::factory()->create(['data_scope' => 'self']));

    expectVisibleUsers($this->actor, [$this->actor, $this->own]);
});

it('unions the scopes of multiple enabled roles', function (): void {
    registerTeamScope();
    $this->actor->assignRole(Role::factory()->create(['data_scope' => 'self']), Role::factory()->create(['data_scope' => 'team']));

    expectVisibleUsers($this->actor, [$this->actor, $this->own, $this->team]);
});

it('ignores the scopes of disabled roles', function (): void {
    $this->actor->assignRole(Role::factory()->create(['data_scope' => 'self']), Role::factory()->disabled()->create(['data_scope' => 'all']));

    expectVisibleUsers($this->actor, [$this->actor, $this->own]);
});

it('ignores a disabled super admin role', function (): void {
    $this->actor->assignRole(Role::factory()->superAdmin()->disabled()->create());

    expectVisibleUsers($this->actor, []);
});

it('shows nothing to users without enabled roles', function (): void {
    $this->actor->assignRole(Role::factory()->disabled()->create(['data_scope' => 'all']));

    expectVisibleUsers($this->actor, []);
    expect(User::factory()->create()->isVisibleTo($this->actor))->toBeFalse();
});

it('shows nothing for scopes that are not registered', function (): void {
    $this->actor->assignRole(Role::factory()->create(['data_scope' => 'department']));

    expectVisibleUsers($this->actor, []);
});

it('keeps the registered scopes of other roles when one scope is not registered', function (): void {
    $this->actor->assignRole(Role::factory()->create(['data_scope' => 'department']), Role::factory()->create(['data_scope' => 'self']));

    expectVisibleUsers($this->actor, [$this->actor, $this->own]);
});

it('keeps the scope inside its own group when combined with other conditions', function (): void {
    registerTeamScope();
    $this->actor->assignRole(Role::factory()->create(['data_scope' => 'self']), Role::factory()->create(['data_scope' => 'team']));

    $names = User::query()->where('name', '!=', 'Own')->visibleTo($this->actor)->orderBy('id')->pluck('name')->all();

    expect($names)->toBe(['Actor', 'Team member']);
});
