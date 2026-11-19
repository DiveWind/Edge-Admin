<?php

namespace App\Core\Monitor\Models;

use App\Core\Monitor\Services\DataMasker;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Carbon\CarbonInterface;
use Database\Factories\Core\Monitor\Models\FailedJobFactory;
use Illuminate\Database\Eloquent\Attributes\{Hidden, Scope, Table};
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Override;

/**
 * 失败任务：框架的失败任务表（config('queue.failed.table')、连接 config('queue.failed.database')）中的一行
 * （多态别名 `failed_job`，见 MonitorServiceProvider；设计文档 §6.6）。
 *
 * - 表由框架的 FailedJobProvider 维护，本模型只读：重试走 RetryFailedJob（queue:retry），删除、清空走 ForgetFailedJob、FlushFailedJobs
 *   （FailedJobProviderInterface 的 forget() / flush()）；
 * - 对外的 id 与路由绑定都是 uuid；只有失败任务驱动为 database-uuids（默认）时表才是失败任务的来源，否则 stored() 什么都取不到；
 * - 输出前脱敏、截断（DataMasker，与操作日志同一规则）：payloadSummary() 去掉序列化的任务对象（data.command，不反序列化）；
 *   exceptionMessage()、exceptionTrace() 替换文本中键名匹配的值并去掉项目根目录。
 *
 * @property int $id
 * @property string $uuid
 * @property string $connection
 * @property string $queue
 * @property string $payload
 * @property string $exception
 * @property CarbonInterface $failed_at
 */
#[Table(timestamps: false)]
#[Hidden(['payload', 'exception'])]
#[RouteKey('uuid')]
class FailedJob extends Model
{
    /** @use HasFactory<FailedJobFactory> */
    use HasFactory, UsesAppTimezone;

    /**
     * 本模型能读取的失败任务驱动。
     */
    public const string DRIVER = 'database-uuids';

    #[Override]
    public function getTable(): string
    {
        return config()->string('queue.failed.table');
    }

    #[Override]
    public function getConnectionName(): ?string
    {
        $connection = config('queue.failed.database');

        return is_string($connection) ? $connection : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    /**
     * 失败任务表是失败任务的来源时（驱动为 database-uuids）的全部记录；其他驱动为空。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function stored(Builder $query): void
    {
        if (config('queue.failed.driver') !== self::DRIVER) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * 路由绑定：按 uuid 在 stored() 中查找。
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    #[Override]
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return is_string($value) ? static::query()->stored()->where($field ?? $this->getRouteKeyName(), $value)->first() : null;
    }

    /**
     * payload 中的任务名（displayName，通常是任务类名）。
     */
    public function displayName(): ?string
    {
        $name = $this->decodedPayload()['displayName'] ?? null;

        return is_string($name) ? $name : null;
    }

    /**
     * payload 摘要：去掉序列化的任务对象（data.command）后脱敏、截断；无法解码时为 []。
     *
     * @return array<array-key, mixed>
     */
    public function payloadSummary(): array
    {
        $payload = $this->decodedPayload();

        if (is_array($payload['data'] ?? null)) {
            unset($payload['data']['command']);
        }

        return resolve(DataMasker::class)->maskArray($payload, config()->integer('core.monitor.failed_job.max_value_length'));
    }

    /**
     * 异常的第一行（类名与消息），脱敏、去掉项目根目录，最长 max_value_length 个字符。
     */
    public function exceptionMessage(): string
    {
        $masker = resolve(DataMasker::class);

        return $masker->truncate($this->sanitizedExceptionLines()[0] ?? '', config()->integer('core.monitor.failed_job.max_value_length'));
    }

    /**
     * 异常第一行之后的调用栈，最多 max_trace_lines 行，超出时末行为 TRUNCATED。
     */
    public function exceptionTrace(): string
    {
        $lines = array_slice($this->sanitizedExceptionLines(), 1);
        $max = config()->integer('core.monitor.failed_job.max_trace_lines');

        if (count($lines) > $max) {
            $lines = [...array_slice($lines, 0, $max), DataMasker::TRUNCATED];
        }

        return implode("\n", $lines);
    }

    /**
     * 脱敏、去掉项目根目录后的异常文本，按行拆分。
     *
     * @return list<string>
     */
    private function sanitizedExceptionLines(): array
    {
        $text = resolve(DataMasker::class)->maskText(str_replace(base_path().DIRECTORY_SEPARATOR, '', $this->exception));

        return preg_split('/\R/', rtrim($text)) ?: [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodedPayload(): array
    {
        $payload = json_decode($this->payload, true);

        return is_array($payload) ? $payload : [];
    }
}
