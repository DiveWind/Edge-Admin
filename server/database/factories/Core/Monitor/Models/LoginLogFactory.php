<?php

namespace Database\Factories\Core\Monitor\Models;

use App\Core\Monitor\Enums\{LoginEvent, LoginFailureReason};
use App\Core\Monitor\Models\LoginLog;
use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 登录日志（与 LogLogin 写入的结构相同）。用 causedBy() 指定账号。
 *
 * @extends Factory<LoginLog>
 */
class LoginLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'log_name' => LoginLog::LOG_NAME,
            'description' => LoginEvent::Login->value,
            'subject_type' => null,
            'subject_id' => null,
            'event' => LoginEvent::Login->value,
            'causer_type' => null,
            'causer_id' => null,
            'attribute_changes' => null,
            'properties' => [
                'username' => fake()->userName(),
                'reason' => null,
                'via_remember' => false,
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'request_id' => Str::uuid7()->toString(),
            ],
        ];
    }

    /**
     * 账号为 $user（causer 与 username 快照）。
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
     * 登录失败（event = failed）及原因。
     */
    public function failed(LoginFailureReason $reason = LoginFailureReason::InvalidCredentials): static
    {
        return $this->state(fn (array $attributes): array => [
            'description' => LoginEvent::Failed->value,
            'event' => LoginEvent::Failed->value,
            'properties' => [...$this->properties($attributes), 'reason' => $reason->value],
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
