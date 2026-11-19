<?php

namespace App\Core\System\Rules;

use App\Core\System\Services\Settings;
use Illuminate\Validation\Rules\File;

/**
 * 上传的附件文件（设计文档 §6.8、§9）：类型白名单与大小上限取自系统参数，所有入口的上传接口共用：
 *
 * ```php
 * 'file' => ['required', new AllowedUpload],
 * ```
 *
 * - 类型：按文件内容探测的 MIME 推断出的扩展名（`mimes`）**且**客户端文件名的扩展名（`extensions`）都必须在
 *   `attachment.allowed_extensions` 中——改扩展名、伪造 Content-Type 都不能通过（框架的 mimes 规则同时拦截 PHP 文件）；
 * - 大小：不超过 `attachment.max_size`（KB）。PHP 的 upload_max_filesize、post_max_size 更小时，文件到不了这里；
 * - 白名单为空（不应出现，系统参数的校验要求至少一个）时一律不通过：默认拒绝。
 *
 * 构造时读取系统参数（每次校验新建一个实例，后台修改后立即生效）。
 */
class AllowedUpload extends File
{
    public function __construct()
    {
        $settings = resolve(Settings::class);
        $extensions = self::allowedExtensions($settings->string('attachment.allowed_extensions'));

        $this->allowedMimetypes = $extensions;
        $this->allowedExtensions = $extensions;
        $this->maximumFileSize = $settings->integer('attachment.max_size');

        if ($extensions === []) {
            $this->customRules = ['prohibited'];
        }
    }

    /**
     * 逗号分隔的扩展名列表（去掉空白、转小写、去重）。
     *
     * @return list<string>
     */
    public static function allowedExtensions(string $setting): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $extension): string => strtolower(trim($extension)),
            explode(',', $setting),
        ), fn (string $extension): bool => $extension !== '')));
    }
}
