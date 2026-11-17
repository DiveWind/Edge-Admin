<?php

namespace App\Services;

use App\Enums\LoginStatus;
use App\Models\{Article, ArticleCategory, Attachment, Department, LoginLog, OperationLog, PersonalAccessToken, Role, User};
use Carbon\CarbonPeriod;
use Illuminate\Cache\CacheManager;
use Illuminate\Container\Attributes\Config;

/**
 * 工作台统计。按日期分组在 PHP 侧完成，保持数据库无关。
 *
 * 汇总类统计（8 项计数、登录趋势、分类 / 部门分布）按 stale-while-revalidate 缓存（Cache::flexible()，
 * 时长见 config('edge.statistics_cache')）：过期后的第一个请求先拿到旧值，重新计算在响应发送后进行（defer），
 * 并由缓存锁保证只有一个请求重算。"最近操作"是带索引的 10 行查询，始终实时。
 */
class DashboardStatistics
{
    public const string CACHE_KEY = 'edge:dashboard';

    protected const int TREND_DAYS = 14;

    protected const int RECENT_OPERATIONS = 10;

    /**
     * @param  array{fresh: int, stale: int}  $cacheTtl
     */
    public function __construct(
        protected CacheManager $cache,
        #[Config('edge.statistics_cache')] protected array $cacheTtl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            ...$this->aggregates(),
            'recent_operations' => $this->recentOperations(),
        ];
    }

    /**
     * @return array{totals: array<string, int>, login_trend: list<array{date: string, success: int, failed: int}>, articles_by_category: array<int, array{category: string, count: int}>, users_by_department: array<int, array{department: string, count: int}>}
     */
    protected function aggregates(): array
    {
        $compute = fn (): array => [
            'totals' => $this->totals(),
            'login_trend' => $this->loginTrend(),
            'articles_by_category' => $this->articlesByCategory(),
            'users_by_department' => $this->usersByDepartment(),
        ];

        if ($this->cacheTtl['fresh'] <= 0) {
            return $compute();
        }

        return $this->cache->flexible(self::CACHE_KEY, [$this->cacheTtl['fresh'], max($this->cacheTtl['fresh'], $this->cacheTtl['stale'])], $compute);
    }

    /**
     * @return array<string, int>
     */
    protected function totals(): array
    {
        return [
            'users' => User::query()->count(),
            'roles' => Role::query()->count(),
            'departments' => Department::query()->count(),
            'articles' => Article::query()->count(),
            'today_logins' => LoginLog::query()->where('status', LoginStatus::Success)->whereDate('created_at', today())->count(),
            'today_operations' => OperationLog::query()->whereDate('created_at', today())->count(),
            'online_sessions' => PersonalAccessToken::query()->online()->count(),
            'attachments' => Attachment::query()->count(),
        ];
    }

    /**
     * 最近 14 天（含今天）登录成功 / 失败次数，缺失日期补 0。
     *
     * @return list<array{date: string, success: int, failed: int}>
     */
    protected function loginTrend(): array
    {
        $start = today()->subDays(self::TREND_DAYS - 1);

        $counts = LoginLog::query()
            ->where('created_at', '>=', $start)
            ->get(['status', 'created_at'])
            ->countBy(fn (LoginLog $log): string => $log->created_at?->toDateString().'|'.$log->status->value);

        $trend = [];

        foreach (CarbonPeriod::create($start, today()) as $day) {
            $date = $day->toDateString();
            $trend[] = [
                'date' => $date,
                'success' => (int) $counts->get($date.'|'.LoginStatus::Success->value, 0),
                'failed' => (int) $counts->get($date.'|'.LoginStatus::Failed->value, 0),
            ];
        }

        return $trend;
    }

    /**
     * @return array<int, array{category: string, count: int}>
     */
    protected function articlesByCategory(): array
    {
        return ArticleCategory::query()
            ->withCount('articles')
            ->orderBy('sort')
            ->get()
            ->map(fn (ArticleCategory $category): array => [
                'category' => $category->name,
                'count' => (int) $category->articles_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{department: string, count: int}>
     */
    protected function usersByDepartment(): array
    {
        return Department::query()
            ->withCount('users')
            ->ordered()
            ->get()
            ->map(fn (Department $department): array => [
                'department' => $department->name,
                'count' => (int) $department->users_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentOperations(): array
    {
        return OperationLog::query()
            ->with('user')
            ->latest('id')
            ->limit(self::RECENT_OPERATIONS)
            ->get()
            ->map(fn (OperationLog $log): array => [
                'id' => $log->id,
                'user' => $log->user?->only('id', 'name'),
                'method' => $log->method,
                'path' => $log->path,
                'summary' => $log->summary,
                'status_code' => $log->status_code,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
