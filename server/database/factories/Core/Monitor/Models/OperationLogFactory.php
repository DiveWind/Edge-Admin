<?php

namespace Database\Factories\Core\Monitor\Models;

use App\Core\Monitor\Models\OperationLog;
use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 操作日志（与 LogOperation 写入的结构相同）。用 causedBy() 指定操作者。
 *
 * @extends Factory<OperationLog>
 */
class OperationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_name' => OperationLog::LOG_NAME,
            'description' => 'admin.system.users.store',
            'subject_type' => null,
            'subject_id' => null,
            'event' => null,
            'causer_type' => null,
            'causer_id' => null,
            'attribute_changes' => null,
            'properties' => [
                'username' => null,
                'method' => 'POST',
                'path' => '/api/admin/system/users',
                'route_parameters' => [],
                'payload' => ['username' => 'alice', 'password' => '******'],
                'status' => 201,
                'duration_ms' => 12,
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'request_id' => Str::uuid7()->toString(),
            ],
        ];
    }

    /**
     * 操作者为 $user（causer 与 username 快照）。
     */
    public function causedBy(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'causer_type' => $user->getMorphClass(),
            'causer_id' => $user->getKey(),
            'properties' => [...$this->properties($attributes), 'username' => $user->username],
        ]);
    }

    /**
     * 合并到 properties 中。
     *
     * @param  array<string, mixed>  $properties
     */
    public function withProperties(array $properties): static
    {
        return $this->state(fn (array $attributes): array => [
            'properties' => [...$this->properties($attributes), ...$properties],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<array-key, mixed>
     */
    private function properties(array $attributes): array
    {
        $properties = $attributes['properties'] ?? [];

        return is_array($properties) ? $properties : [];
    }
}
