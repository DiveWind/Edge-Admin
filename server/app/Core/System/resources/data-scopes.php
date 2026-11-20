<?php

use App\Core\System\Services\{AllDataScope, SelfDataScope};

/*
|--------------------------------------------------------------------------
| 数据权限范围（DataScopeRegistry，设计文档 §5.5、§8.4）
|--------------------------------------------------------------------------
|
| 提供者类的列表（实现 DataScopeProvider，key 由提供者给出，由容器解析）。Core 默认提供“全部”与“仅本人”。
|
*/

return [
    AllDataScope::class,
    SelfDataScope::class,
];
