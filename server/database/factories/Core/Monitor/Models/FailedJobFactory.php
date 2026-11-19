<?php

namespace Database\Factories\Core\Monitor\Models;

use App\Core\Monitor\Models\FailedJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 失败任务表中的一行（与框架的 DatabaseUuidFailedJobProvider 写入的格式相同）。
 *
 * @extends Factory<FailedJob>
 */
class FailedJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => (string) json_encode([
                'uuid' => $uuid,
                'displayName' => 'App\\Jobs\\SendReport',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'maxTries' => null,
                'timeout' => null,
                'data' => ['commandName' => 'App\\Jobs\\SendReport', 'command' => 'O:19:"App\\Jobs\\SendReport":0:{}'],
            ]),
            'exception' => 'RuntimeException: The report service is down. in '.base_path('app/Jobs/SendReport.php').":42\nStack trace:\n#0 ".base_path('vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php').'(36): App\\Jobs\\SendReport->handle()'."\n#1 {main}",
            'failed_at' => now(),
        ];
    }
}
