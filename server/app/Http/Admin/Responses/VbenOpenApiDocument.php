<?php

namespace App\Http\Admin\Responses;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\{OpenApi, Operation, Reference, Response, Schema};
use Dedoc\Scramble\Support\Generator\Types\{ArrayType, IntegerType, NullType, ObjectType, StringType, Type};
use LogicException;

/**
 * 本地 OpenAPI 文档（dedoc/scramble，只在开发环境安装与开放，设计文档 §12.2）中后台入口的响应格式。
 *
 * Scramble 按控制器的返回值推断响应，不知道 FormatResponse 会把它们包装为 Vben 格式（§6.1），这里在生成后统一改写：
 * - 2xx：`{ code: 0, data, message }`；API Resource 的 `{ data }` 取出其中的 data；分页器（`{ data, links, meta }`）改为
 *   `{ items, total }`；204 改为 200 + `data: null`（后台入口不返回 204）；
 * - 其他状态码（含 components 中的 401 / 403 / 404 / 422 等）：在原有的 `message`（422 另有 `errors`）之外加上
 *   `code`（与 HTTP 状态码相同）与 `data: null`。
 *
 * 只改响应的结构，不改推断出的字段；推断不准的地方见 server/README.md 的“接口文档”。
 */
class VbenOpenApiDocument implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $this->transformOperation($operation);
            }
        }

        foreach ($document->components->responses as $response) {
            if ($response instanceof Response) {
                $this->wrapError($response);
            }
        }
    }

    private function transformOperation(Operation $operation): void
    {
        foreach ($operation->responses ?? [] as $response) {
            if (! $response instanceof Response) {
                continue;
            }

            $code = (int) $response->code;

            if ($code === 204) {
                $response->setCode(200);
                $response->setDescription('`data: null`');
                $response->content = ['application/json' => $this->schema($this->envelope(new NullType))];
            } elseif ($code >= 200 && $code < 300) {
                $this->wrapSuccess($response);
            } else {
                $this->wrapError($response);
            }
        }
    }

    private function wrapSuccess(Response $response): void
    {
        $schema = $response->content['application/json'] ?? null;
        $type = $schema instanceof Schema ? $schema->type : ($schema instanceof Reference ? $schema : null);

        if ($type instanceof ObjectType && $type->hasProperty('code') && $type->hasProperty('message')) {
            return;
        }

        $response->content = ['application/json' => $this->schema($this->envelope($this->data($type)))];
    }

    /**
     * 控制器的返回值 → Vben 的 data：分页器改为 `{ items, total }`，API Resource 的 `{ data }` 取出 data，空响应为 null。
     */
    private function data(?Type $type): Type
    {
        if (! $type instanceof ObjectType) {
            return $type ?? new NullType;
        }

        $properties = array_keys($type->properties);
        $data = $type->properties['data'] ?? null;

        if ($data instanceof Type && in_array('links', $properties, true) && in_array('meta', $properties, true)) {
            $items = new ArrayType;
            $items->items = $data instanceof ArrayType ? $data->items : $data;

            $page = new ObjectType;
            $page->addProperty('items', $items);
            $page->addProperty('total', new IntegerType()->setDescription('全部记录数'));
            $page->setRequired(['items', 'total']);

            return $page;
        }

        return $data instanceof Type && $properties === ['data'] ? $data : $type;
    }

    private function envelope(Type $data): ObjectType
    {
        $envelope = new ObjectType;
        $envelope->addProperty('code', new IntegerType()->examples([0])->setDescription('成功为 0'));
        $envelope->addProperty('data', $data);
        $envelope->addProperty('message', new StringType()->examples(['ok']));
        $envelope->setRequired(['code', 'data', 'message']);

        return $envelope;
    }

    /**
     * Scramble 的 Schema::fromType() 没有声明返回类型。
     */
    private function schema(Type $type): Schema
    {
        $schema = Schema::fromType($type);

        return $schema instanceof Schema ? $schema : throw new LogicException('Schema::fromType() did not return a schema.');
    }

    private function wrapError(Response $response): void
    {
        $schema = $response->content['application/json'] ?? null;
        $type = $schema instanceof Schema ? $schema->type : null;

        if (! $type instanceof ObjectType) {
            $type = new ObjectType;
            $type->addProperty('message', new StringType);
            $response->content = ['application/json' => $this->schema($type)];
        }

        if ($type->hasProperty('code')) {
            return;
        }

        $type->addProperty('code', new IntegerType()->setDescription('与 HTTP 状态码相同'));
        $type->addProperty('data', new NullType);
        $type->addRequired(['code', 'data', 'message']);
    }
}
