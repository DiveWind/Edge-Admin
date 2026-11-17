<?php

namespace App\Listeners;

use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Events\DiagnosingHealth;

/**
 * 健康检查（`GET /up`，bootstrap/app.php 的 `health`）：框架在该路由上派发 DiagnosingHealth，
 * 这里确认默认数据库连接可用；连接失败时抛出异常，/up 返回 500，负载均衡 / 容器编排即可摘除该实例。
 */
class CheckDatabaseConnection
{
    public function __construct(protected DatabaseManager $db) {}

    /**
     * Handle the event.
     */
    public function handle(DiagnosingHealth $event): void
    {
        $this->db->connection()->getPdo();
    }
}
