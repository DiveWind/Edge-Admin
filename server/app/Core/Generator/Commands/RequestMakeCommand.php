<?php

namespace App\Core\Generator\Commands;

use Illuminate\Console\Attributes\{Description, Signature};
use Override;

/**
 * core:make-request：入口中的 Form Request（app/Http/<入口>/<模块>/Requests），只做输入格式校验（设计文档 §5.4）。
 * `--list` 生成列表请求：继承入口的 Requests\ListRequest（page、pageSize、sort、filter[]，设计文档 §6.3）。
 */
#[Signature('core:make-request
    {name : 请求类名，如 StoreArticle（生成 StoreArticleRequest）}
    {--list : 列表请求，继承入口的 ListRequest}
    '.self::ENTRY_OPTIONS.'
    '.self::MODULE_OPTIONS)]
#[Description('在入口中生成 Form Request（--list 生成继承 ListRequest 的列表请求）')]
class RequestMakeCommand extends EntryGeneratorCommand
{
    /**
     * The type of class being generated.
     *
     * @var string
     */
    #[Override]
    protected $type = 'Request';

    protected function stubName(): string
    {
        return $this->option('list') ? 'request.list' : 'request';
    }

    protected function subNamespace(): string
    {
        return 'Requests';
    }

    protected function suffix(): string
    {
        return 'Request';
    }

    protected function example(): string
    {
        return 'StoreArticle';
    }

    /**
     * 输入错误时返回错误信息：列表请求要求入口提供 ListRequest。
     */
    protected function inputError(): ?string
    {
        if (($error = parent::inputError()) !== null) {
            return $error;
        }

        return $this->option('list') && ! $this->files->exists($this->entryPath('Requests/ListRequest.php'))
            ? "入口 {$this->appKey()} 没有 Requests/ListRequest.php，不能生成列表请求（--list）。"
            : null;
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(string $class): array
    {
        return [...parent::replacements($class), 'namespacedListRequest' => $this->entryNamespace().'\\Requests\\ListRequest'];
    }
}
