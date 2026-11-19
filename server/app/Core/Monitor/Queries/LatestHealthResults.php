<?php

namespace App\Core\Monitor\Queries;

use Carbon\CarbonImmutable;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\{StoredCheckResult, StoredCheckResults};

/**
 * 最近一次健康检查的结果（GET /monitor/health，设计文档 §6.6）：读取 spatie/laravel-health 的结果存储（config('health.result_stores')，
 * 本项目为缓存）。尚无结果时 finished_at 为 null、checks 为 []。
 *
 * label、notification_message、short_summary 是包的英文文案，前端按 name 翻译标签；meta 原样输出（检查项的数值，如磁盘使用率）。
 */
class LatestHealthResults
{
    public function __construct(private readonly ResultStore $store) {}

    /**
     * Execute the query.
     *
     * @return array{finished_at: string|null, checks: list<array{name: string, label: string, status: string, short_summary: string, notification_message: string, meta: array<array-key, mixed>}>}
     */
    public function get(): array
    {
        $results = $this->store->latestResults();

        if (! $results instanceof StoredCheckResults) {
            return ['finished_at' => null, 'checks' => []];
        }

        return [
            'finished_at' => CarbonImmutable::instance($results->finishedAt)->setTimezone(config()->string('app.timezone'))->toIso8601String(),
            'checks' => array_values($results->storedCheckResults
                ->map(fn (StoredCheckResult $check): array => [
                    'name' => $check->name,
                    'label' => $check->label,
                    'status' => $check->status,
                    'short_summary' => $check->shortSummary,
                    'notification_message' => $check->notificationMessage ?? '',
                    'meta' => $check->meta,
                ])
                ->all()),
        ];
    }
}
