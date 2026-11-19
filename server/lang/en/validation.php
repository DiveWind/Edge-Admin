<?php

/*
|--------------------------------------------------------------------------
| Validation messages (English)
|--------------------------------------------------------------------------
|
| 校验规则的文案沿用框架自带英文（vendor 中的 lang/en），这里只放字段名；
| 与 lang/zh_CN/validation.php 的 attributes 保持同样的键（tests/Arch/TranslationsTest.php 检查）。
|
*/

return [

    'attributes' => [
        'page' => 'page',
        'pageSize' => 'page size',
        'sort' => 'sort',
        'filter' => 'filter',
        'name' => 'name',
        'email' => 'email',
        'password' => 'password',
        'username' => 'username',
        'avatar_id' => 'avatar',
        'file' => 'file',
        'current_password' => 'current password',
        'code' => 'authentication code',
        'recovery_code' => 'recovery code',
        'token' => 'reset token',
    ],

];
