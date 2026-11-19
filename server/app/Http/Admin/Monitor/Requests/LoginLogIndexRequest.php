<?php

namespace App\Http\Admin\Monitor\Requests;

use App\Core\Monitor\Enums\{LoginEvent, LoginFailureReason};
use App\Http\Admin\Requests\ListRequest;
use BackedEnum;

/**
 * 登录日志列表的查询参数（GET /monitor/login-logs，设计文档 §6.3、§6.6）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；event、reason 可用逗号传多个（如 `failed,lockout`），每一项都必须是已知的值。
 * 允许的筛选与排序字段由控制器中 query-builder 的白名单决定，其他参数返回 422；created_at 区间由 DateTimeRangeFilter 校验。
 */
class LoginLogIndexRequest extends ListRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'filter.user_id' => ['sometimes', 'integer', 'min:1'],
            'filter.event' => ['sometimes', 'string', 'regex:'.$this->listOf(LoginEvent::cases())],
            'filter.reason' => ['sometimes', 'string', 'regex:'.$this->listOf(LoginFailureReason::cases())],
            'filter.request_id' => ['sometimes', 'string', 'max:255'],
            'filter.username' => ['sometimes', 'string', 'max:255'],
            'filter.ip_address' => ['sometimes', 'string', 'max:45'],
            'filter.created_at' => ['sometimes', 'string', 'max:100'],
        ];
    }

    /**
     * 逗号分隔的枚举值列表的正则（如 `/^(login|logout)(,(login|logout))*$/`）。
     *
     * @param  list<BackedEnum>  $cases
     */
    private function listOf(array $cases): string
    {
        $values = implode('|', array_map(fn (BackedEnum $case): string => preg_quote((string) $case->value, '/'), $cases));

        return "/^({$values})(,({$values}))*$/";
    }
}
