<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()->pluck('id', 'name');
        $roles = Role::query()->pluck('id', 'code');
        $positions = Position::query()->pluck('id', 'code');

        $admin = $this->createUser('admin', '超级管理员', '总公司', 'super-admin', 'ceo', $departments, $roles, $positions);
        $editor = $this->createUser('editor', '内容编辑', '研发中心', 'editor', 'pm', $departments, $roles, $positions);
        $this->createUser('viewer', '只读访客', '前端组', 'viewer', 'staff', $departments, $roles, $positions);
        $marketing = $this->createUser('marketing', '市场主管', '市场部', 'marketing-manager', 'marketing', $departments, $roles, $positions);

        Department::query()->whereKey($departments['总公司'])->update(['leader_id' => $admin->id]);
        Department::query()->whereKey($departments['研发中心'])->update(['leader_id' => $editor->id]);
        Department::query()->whereKey($departments['市场部'])->update(['leader_id' => $marketing->id]);

        $members = [
            '前端组' => 'developer', '后端组' => 'developer', '测试组' => 'qa',
            '华东区' => 'marketing', '华南区' => 'marketing', '财务部' => 'staff', '人事部' => 'staff',
        ];

        foreach ($members as $department => $position) {
            User::factory()
                ->count(2)
                ->sequence(fn (Sequence $sequence): array => $sequence->index % 2 === 0 ? [
                    'last_login_at' => now()->subHours(fake()->numberBetween(1, 72)),
                    'last_login_ip' => fake()->ipv4(),
                ] : [])
                ->create(['department_id' => $departments[$department]])
                ->each(function (User $user) use ($roles, $positions, $position): void {
                    $user->roles()->sync([$roles['viewer']]);
                    $user->positions()->sync([$positions[$position]]);
                });
        }

        User::factory()->disabled()->create(['username' => 'disabled', 'name' => '已禁用账号', 'department_id' => $departments['人事部']]);
    }

    /**
     * @param  Collection<string, int>  $departments
     * @param  Collection<string, int>  $roles
     * @param  Collection<string, int>  $positions
     */
    protected function createUser(
        string $username,
        string $name,
        string $department,
        string $role,
        string $position,
        Collection $departments,
        Collection $roles,
        Collection $positions,
    ): User {
        $user = User::factory()->recentlyLoggedIn()->create([
            'username' => $username,
            'name' => $name,
            'email' => "{$username}@example.com",
            'department_id' => $departments[$department],
        ]);

        $user->roles()->sync([$roles[$role]]);
        $user->positions()->sync([$positions[$position]]);

        return $user;
    }
}
