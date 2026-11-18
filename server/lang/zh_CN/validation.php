<?php

/*
|--------------------------------------------------------------------------
| 校验提示（简体中文）
|--------------------------------------------------------------------------
|
| 只翻译常用规则；未列出的规则按键回退到 fallback_locale（en）。字段的中文名写在下方 attributes，
| 模块专用的字段名放在模块自己的语言包中（设计文档 §4.3）。
|
*/

return [

    'accepted' => ':attribute 必须接受。',
    'active_url' => ':attribute 不是有效的网址。',
    'after' => ':attribute 必须晚于 :date。',
    'after_or_equal' => ':attribute 不能早于 :date。',
    'alpha' => ':attribute 只能包含字母。',
    'alpha_dash' => ':attribute 只能包含字母、数字、短横线和下划线。',
    'alpha_num' => ':attribute 只能包含字母和数字。',
    'array' => ':attribute 必须是数组。',
    'before' => ':attribute 必须早于 :date。',
    'before_or_equal' => ':attribute 不能晚于 :date。',
    'between' => [
        'array' => ':attribute 必须有 :min 到 :max 项。',
        'file' => ':attribute 必须在 :min 到 :max KB 之间。',
        'numeric' => ':attribute 必须在 :min 到 :max 之间。',
        'string' => ':attribute 必须是 :min 到 :max 个字符。',
    ],
    'boolean' => ':attribute 必须是布尔值。',
    'confirmed' => ':attribute 两次输入不一致。',
    'current_password' => '密码错误。',
    'date' => ':attribute 不是有效的日期。',
    'date_format' => ':attribute 的格式必须是 :format。',
    'decimal' => ':attribute 必须有 :decimal 位小数。',
    'different' => ':attribute 和 :other 不能相同。',
    'digits' => ':attribute 必须是 :digits 位数字。',
    'digits_between' => ':attribute 必须是 :min 到 :max 位数字。',
    'distinct' => ':attribute 有重复的值。',
    'email' => ':attribute 不是有效的邮箱地址。',
    'ends_with' => ':attribute 必须以 :values 结尾。',
    'enum' => '所选的 :attribute 无效。',
    'exists' => '所选的 :attribute 不存在。',
    'file' => ':attribute 必须是文件。',
    'filled' => ':attribute 不能为空。',
    'gt' => [
        'array' => ':attribute 必须多于 :value 项。',
        'file' => ':attribute 必须大于 :value KB。',
        'numeric' => ':attribute 必须大于 :value。',
        'string' => ':attribute 必须多于 :value 个字符。',
    ],
    'gte' => [
        'array' => ':attribute 至少需要 :value 项。',
        'file' => ':attribute 不能小于 :value KB。',
        'numeric' => ':attribute 不能小于 :value。',
        'string' => ':attribute 至少需要 :value 个字符。',
    ],
    'image' => ':attribute 必须是图片。',
    'in' => '所选的 :attribute 无效。',
    'integer' => ':attribute 必须是整数。',
    'ip' => ':attribute 不是有效的 IP 地址。',
    'json' => ':attribute 必须是有效的 JSON 字符串。',
    'list' => ':attribute 必须是列表。',
    'lt' => [
        'array' => ':attribute 必须少于 :value 项。',
        'file' => ':attribute 必须小于 :value KB。',
        'numeric' => ':attribute 必须小于 :value。',
        'string' => ':attribute 必须少于 :value 个字符。',
    ],
    'lte' => [
        'array' => ':attribute 不能多于 :value 项。',
        'file' => ':attribute 不能大于 :value KB。',
        'numeric' => ':attribute 不能大于 :value。',
        'string' => ':attribute 不能多于 :value 个字符。',
    ],
    'max' => [
        'array' => ':attribute 不能多于 :max 项。',
        'file' => ':attribute 不能大于 :max KB。',
        'numeric' => ':attribute 不能大于 :max。',
        'string' => ':attribute 不能多于 :max 个字符。',
    ],
    'mimes' => ':attribute 必须是 :values 类型的文件。',
    'mimetypes' => ':attribute 必须是 :values 类型的文件。',
    'min' => [
        'array' => ':attribute 至少需要 :min 项。',
        'file' => ':attribute 不能小于 :min KB。',
        'numeric' => ':attribute 不能小于 :min。',
        'string' => ':attribute 至少需要 :min 个字符。',
    ],
    'not_in' => '所选的 :attribute 无效。',
    'not_regex' => ':attribute 的格式无效。',
    'numeric' => ':attribute 必须是数字。',
    'password' => [
        'letters' => ':attribute 必须包含字母。',
        'mixed' => ':attribute 必须同时包含大写和小写字母。',
        'numbers' => ':attribute 必须包含数字。',
        'symbols' => ':attribute 必须包含符号。',
        'uncompromised' => ':attribute 出现在已泄露的密码库中，请更换。',
    ],
    'present' => ':attribute 必须存在。',
    'prohibited' => '不允许提交 :attribute。',
    'regex' => ':attribute 的格式无效。',
    'required' => ':attribute 不能为空。',
    'required_if' => '当 :other 为 :value 时，:attribute 不能为空。',
    'required_unless' => '除非 :other 为 :values，否则 :attribute 不能为空。',
    'required_with' => '当 :values 存在时，:attribute 不能为空。',
    'required_without' => '当 :values 不存在时，:attribute 不能为空。',
    'same' => ':attribute 必须与 :other 相同。',
    'size' => [
        'array' => ':attribute 必须是 :size 项。',
        'file' => ':attribute 必须是 :size KB。',
        'numeric' => ':attribute 必须是 :size。',
        'string' => ':attribute 必须是 :size 个字符。',
    ],
    'starts_with' => ':attribute 必须以 :values 开头。',
    'string' => ':attribute 必须是字符串。',
    'timezone' => ':attribute 不是有效的时区。',
    'ulid' => ':attribute 不是有效的 ULID。',
    'unique' => ':attribute 已存在。',
    'uploaded' => ':attribute 上传失败。',
    'url' => ':attribute 不是有效的网址。',
    'uuid' => ':attribute 不是有效的 UUID。',

    /*
    |--------------------------------------------------------------------------
    | 字段名
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'page' => '页码',
        'pageSize' => '每页条数',
        'sort' => '排序',
        'filter' => '筛选条件',
        'name' => '名称',
        'email' => '邮箱',
        'password' => '密码',
    ],

];
