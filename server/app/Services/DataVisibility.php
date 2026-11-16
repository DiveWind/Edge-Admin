<?php

namespace App\Services;

/**
 * 数据权限解析结果。
 */
final readonly class DataVisibility
{
    /**
     * @param  list<int>  $departmentIds  可见的部门 id
     * @param  bool  $self  是否可见本人创建的数据
     */
    public function __construct(
        public bool $all,
        public array $departmentIds = [],
        public bool $self = false,
    ) {}

    public static function everything(): self
    {
        return new self(all: true);
    }
}
