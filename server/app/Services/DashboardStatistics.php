<?php

namespace App\Services;

use App\Enums\LoginStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\LoginLog;
use App\Models\OperationLog;
use App\Models\PersonalAccessToken;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonPeriod;

/**
 * 工作台统计。按日期分组在 PHP 侧完成，保持数据库无关。
 */
class DashboardStatistics
{
    protected const int TREND_DAYS = 14;

    protected const int RECENT_OPERATIONS = 10;

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'totals' => $this->totals(),
            'login_trend' => $this->loginTrend(),
            'articles_by_category' => $this->articlesByCategory(),
            'users_by_department' => $this->usersByDepartment(),
            'recent_operations' => $this->recentOperations(),
        ];
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
