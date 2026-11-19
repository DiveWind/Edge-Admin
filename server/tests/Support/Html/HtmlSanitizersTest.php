<?php

use App\Support\Html\HtmlSanitizers;

/*
|--------------------------------------------------------------------------
| 富文本净化的档位（设计文档 §9）
|--------------------------------------------------------------------------
|
| 各档位共用的规则（脚本、事件属性、危险协议）由 tests/Support/Eloquent/Casts/SanitizedHtmlTest.php 覆盖；这里覆盖档位之间的差异与配置。
|
*/

it('removes images and other media in the basic profile but keeps text formatting and links', function (): void {
    $html = resolve(HtmlSanitizers::class)->sanitize(
        '<p><strong>Bold</strong> <a href="https://example.com">link</a></p><img src="https://example.com/a.png"><video src="https://example.com/a.mp4"></video><table><tr><td>Cell</td></tr></table>',
        'basic',
    );

    expect($html)
        ->toContain('<strong>Bold</strong>')
        ->toContain('<a href="https://example.com" rel="noopener noreferrer">link</a>')
        ->toContain('<td>Cell</td>')
        ->not->toContain('<img')
        ->not->toContain('<video');
});

it('keeps http(s) images in the rich profile, which is the default', function (?string $profile): void {
    expect(resolve(HtmlSanitizers::class)->sanitize('<img src="https://example.com/a.png" alt="a">', $profile))
        ->toBe('<img src="https://example.com/a.png" alt="a" />');
})->with(['default' => null, 'rich' => 'rich']);

it('removes scripts and event handlers in every profile', function (string $profile): void {
    $html = resolve(HtmlSanitizers::class)->sanitize('<p onclick="alert(1)">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>', $profile);

    expect($html)->toBe('<p>Hi</p><a rel="noopener noreferrer">x</a>');
})->with(['basic', 'rich']);

it('only keeps images from the configured hosts when media hosts are set', function (): void {
    config(['core.html.profiles.rich.media_hosts' => ['cdn.example.com']]);

    $html = resolve(HtmlSanitizers::class)->sanitize('<img src="https://cdn.example.com/a.png"><img src="https://evil.example.org/b.png">', 'rich');

    expect($html)->toContain('src="https://cdn.example.com/a.png"')->not->toContain('evil.example.org');
});

it('rejects an undefined profile', function (): void {
    resolve(HtmlSanitizers::class)->sanitize('<p>Hi</p>', 'unknown');
})->throws(InvalidArgumentException::class, 'The HTML sanitizer profile [unknown] is not defined');

it('is a singleton that reuses the built sanitizer of a profile', function (): void {
    $sanitizers = resolve(HtmlSanitizers::class);

    expect(resolve(HtmlSanitizers::class))->toBe($sanitizers)
        ->and($sanitizers->profile('basic'))->toBe($sanitizers->profile('basic'))
        ->and($sanitizers->profile('basic'))->not->toBe($sanitizers->profile('rich'));
});
