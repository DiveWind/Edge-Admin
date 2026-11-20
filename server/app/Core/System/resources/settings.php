<?php

/*
|--------------------------------------------------------------------------
| System 模块的系统参数分组（设计文档 §6.7）
|--------------------------------------------------------------------------
|
| 在 SystemServiceProvider::boot() 中注册到 SettingRegistry，由 `php artisan core:sync-settings` 写入 settings 表；
| 代码用 App\Core\System\Services\Settings 读取（按声明的类型，库中没有改过的值时使用这里的默认值）。
|
| 格式：分组 => ['title', 'order', 'settings' => [参数名 => 声明]]，参数的键为 `<分组>.<参数名>`：
| - type：string、text、int、float、bool、json（App\Core\System\Enums\SettingType）；
| - name、remark：前端 i18n key 或纯文本，后端不翻译；
| - default（默认值）或 config（作为默认值来源的 config 键），二者选一；
| - public：是否出现在免登录的公开参数接口（GET /settings/public）中，默认 false——只公开登录页需要的内容；
| - rules：追加在类型规则之后的校验规则；
| - dict：可选，引用的字典类型编码（只用于 string、int）：值必须是该字典的启用项，前端按它渲染下拉。
|
| 只放管理员在运行时调整的业务参数；部署级的配置留在 config 与环境变量（§5.8）。
|
*/

return [
    'site' => [
        'title' => 'system.setting.site.title',
        'order' => 10,
        'settings' => [
            'name' => [
                'type' => 'string',
                'name' => 'system.setting.site.name',
                'config' => 'app.name',
                'public' => true,
            ],
            'login_notice' => [
                'type' => 'text',
                'name' => 'system.setting.site.loginNotice',
                'remark' => 'system.setting.site.loginNoticeRemark',
                'default' => '',
                'public' => true,
            ],
        ],
    ],
    'auth' => [
        'title' => 'system.setting.auth.title',
        'order' => 20,
        'settings' => [
            // 登录与两步验证的限流次数（设计文档 §7.2）：每次请求时读取，修改后立即生效
            'login_rate_limit' => [
                'type' => 'int',
                'name' => 'system.setting.auth.loginRateLimit',
                'remark' => 'system.setting.auth.loginRateLimitRemark',
                'config' => 'core.system.auth.login_rate_limit',
                'rules' => ['min:1', 'max:1000'],
            ],
        ],
    ],
    'attachment' => [
        'title' => 'system.setting.attachment.title',
        'order' => 30,
        'settings' => [
            // 上传的类型白名单（设计文档 §6.8）：按文件内容探测出的扩展名与客户端的扩展名都必须在其中（AllowedUpload）。
            // 纯文本类（csv 等）的内容常被探测为 txt，允许 csv 时也要允许 txt
            'allowed_extensions' => [
                'type' => 'string',
                'name' => 'system.setting.attachment.allowedExtensions',
                'remark' => 'system.setting.attachment.allowedExtensionsRemark',
                'default' => 'jpg,jpeg,png,gif,webp,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip',
                'rules' => ['regex:/^[a-z0-9]+(,[a-z0-9]+)*$/'],
            ],
            // 上传大小上限（KB）；PHP 的 upload_max_filesize、post_max_size 与 Web 服务器的请求体上限须不小于它
            'max_size' => [
                'type' => 'int',
                'name' => 'system.setting.attachment.maxSize',
                'remark' => 'system.setting.attachment.maxSizeRemark',
                'default' => 10240,
                'rules' => ['min:1', 'max:2097152'],
            ],
        ],
    ],
];
