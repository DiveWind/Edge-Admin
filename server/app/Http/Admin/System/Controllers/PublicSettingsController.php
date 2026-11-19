<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Services\Settings;

/**
 * 公开参数（GET /settings/public，设计文档 §6.7）：**不需要登录**，前端在登录前读取站点名称、登录页文案等。
 *
 * - 只含声明为 public 的参数 `{ 键: 值 }`（以代码中的声明为准，不读库中的 is_public）；没有时为 `[]`；
 * - 读取 Settings 的缓存；限流为 admin 入口的总限流（未登录按 IP）；缓存头为框架默认的 `no-cache, private`
 *   （修改后立即生效，响应可能带会话 Cookie）。
 */
class PublicSettingsController
{
    /**
     * Handle the incoming request.
     *
     * @return array<string, mixed>
     */
    public function __invoke(Settings $settings): array
    {
        return $settings->public();
    }
}
