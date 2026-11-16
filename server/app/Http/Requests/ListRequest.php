<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 分页列表请求基类：校验 page / per_page / sort（白名单）及子类声明的过滤字段。
 */
abstract class ListRequest extends FormRequest
{
    /**
     * 允许排序的字段，`sort=-created_at` 表示倒序。
     *
     * @var list<string>
     */
    protected array $sortable = ['id', 'created_at'];

    protected string $defaultSort = '-id';

    /**
     * 过滤字段的校验规则。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    abstract protected function filterRules(): array;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sorts = collect($this->sortable)->flatMap(fn (string $field): array => [$field, "-{$field}"])->all();

        return [
            ...$this->filterRules(),
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config()->integer('edge.pagination.max_per_page')],
            'sort' => ['sometimes', 'string', Rule::in($sorts)],
        ];
    }

    /**
     * 已校验的过滤字段。
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only(array_keys($this->filterRules()));
    }

    public function perPage(): int
    {
        return $this->integer('per_page', config()->integer('edge.pagination.per_page'));
    }

    /**
     * 按 sort 参数排序，并以 id 倒序作为稳定的次级排序。
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function applySort(Builder $query): Builder
    {
        $sort = $this->string('sort', $this->defaultSort)->toString();
        $column = ltrim($sort, '-');

        $query->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc');

        if ($column !== 'id') {
            $query->orderByDesc('id');
        }

        return $query;
    }
}
