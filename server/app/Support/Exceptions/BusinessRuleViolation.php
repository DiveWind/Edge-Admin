<?php

namespace App\Support\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * 业务规则被违反（设计文档 §5.4）：领域层（Core、Modules）用它表示“请求本身合法，但当前状态不允许这样做”，
 * 如只有草稿可以发布、最后一个启用的超管不能删除、并发时状态已变。
 *
 * 领域层不依赖 HTTP：不抛 Symfony 的 HttpException，状态码由各入口决定——后台入口（Admin）映射为 422 + message
 * （带字段时再加 `errors.<字段>`，与 ValidationException 的输出相同，VbenResponse::fromException()）。
 * 命令行、队列中按普通异常处理（message 即提示）。属于预期内的结果，不写入错误日志（ShouldntReport）。
 *
 * 输入校验类的错误（某个字段的值不合法）继续用 ValidationException；本异常用于与具体字段无关、或字段只是“挂靠位置”的业务规则。
 */
class BusinessRuleViolation extends RuntimeException implements ShouldntReport
{
    /**
     * @param  string  $message  已翻译的提示
     * @param  string|null  $field  错误挂靠的字段；null 时只有 message（§6.1）
     */
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
