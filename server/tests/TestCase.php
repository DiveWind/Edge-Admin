<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Symfony 测试请求默认带 `Accept-Language: en-us`，这里与 phpunit.xml 的 APP_LOCALE 保持一致；
     * 多语言行为见 LocaleTest。
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'zh-CN');
    }
}
