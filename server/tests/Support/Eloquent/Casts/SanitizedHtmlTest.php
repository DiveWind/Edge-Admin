<?php

use App\Support\Eloquent\Casts\SanitizedHtml;
use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| 富文本：写入属性时净化（设计文档 §6.9、§9）
|--------------------------------------------------------------------------
*/

/**
 * 使用 SanitizedHtml 的模型（不入库）。
 */
function richTextModel(): Model
{
    return new class extends Model
    {
        protected function casts(): array
        {
            return ['content' => SanitizedHtml::class];
        }
    };
}

it('removes scripts, styles, frames and event handlers', function (string $html, string $forbidden): void {
    $model = richTextModel();
    $model->setAttribute('content', "<p>Hello</p>{$html}");

    expect($model->getAttributes()['content'])->toContain('<p>Hello</p>')->not->toContain($forbidden)
        ->and($model->getAttribute('content'))->not->toContain($forbidden);
})->with([
    'script' => ['<script>alert(1)</script>', '<script'],
    'event handler' => ['<img src="https://example.com/a.png" onerror="alert(1)">', 'onerror'],
    'javascript link' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
    'data image' => ['<img src="data:image/png;base64,AAAA">', 'data:'],
    'style' => ['<style>body{display:none}</style>', '<style'],
    'iframe' => ['<iframe src="https://example.com"></iframe>', '<iframe'],
    'svg' => ['<svg onload="alert(1)"></svg>', 'onload'],
]);

it('keeps the safe markup and hardens links', function (): void {
    $model = richTextModel();
    $model->setAttribute('content', '<h2>Title</h2><p><strong>Bold</strong> <a href="https://example.com">link</a></p><ul><li>One</li></ul><img src="https://example.com/a.png" alt="a">');

    expect($model->getAttribute('content'))
        ->toContain('<h2>Title</h2>')
        ->toContain('<strong>Bold</strong>')
        ->toContain('<a href="https://example.com" rel="noopener noreferrer">link</a>')
        ->toContain('<li>One</li>')
        ->toContain('<img src="https://example.com/a.png" alt="a" />');
});

it('keeps null as null', function (): void {
    $model = richTextModel();
    $model->setAttribute('content', null);

    expect($model->getAttribute('content'))->toBeNull();
});
