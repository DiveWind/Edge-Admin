<?php

use App\Core\System\Models\{DictItem, DictType};
use App\Core\System\Rules\InDict;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| InDict：值必须是字典类型下的启用项（设计文档 §6.7）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $type = DictType::factory()->create(['code' => 'content.article_source']);
    DictItem::factory()->for($type, 'type')->create(['value' => 'original']);
    DictItem::factory()->for($type, 'type')->create(['value' => '1']);
    DictItem::factory()->for($type, 'type')->disabled()->create(['value' => 'reprint']);

    $disabled = DictType::factory()->disabled()->create(['code' => 'customer_level']);
    DictItem::factory()->for($disabled, 'type')->create(['value' => 'vip']);
});

/**
 * 用 InDict 校验一个值，返回是否通过。
 */
function passesInDict(string $type, mixed $value): bool
{
    return Validator::make(['source' => $value], ['source' => ['required', new InDict($type)]])->passes();
}

it('accepts the values of enabled items, comparing integers as strings', function (mixed $value): void {
    expect(passesInDict('content.article_source', $value))->toBeTrue();
})->with(['string value' => 'original', 'integer value' => 1, 'numeric string' => '1']);

it('rejects values that are not enabled items of an enabled type', function (string $type, mixed $value): void {
    expect(passesInDict($type, $value))->toBeFalse();
})->with([
    'unknown value' => ['content.article_source', 'translated'],
    'disabled item' => ['content.article_source', 'reprint'],
    'disabled type' => ['customer_level', 'vip'],
    'unknown type' => ['missing', 'original'],
    'array' => ['content.article_source', ['original']],
    'boolean' => ['content.article_source', true],
]);

it('reports the standard in message', function (): void {
    $validator = Validator::make(['source' => 'translated'], ['source' => [new InDict('content.article_source')]]);

    expect($validator->errors()->first('source'))->toBe('The selected source is invalid.');
});

it('also accepts the values given to allowing(), such as the current value of the record, even if they are no longer enabled', function (mixed $value, bool $passes): void {
    $rule = new InDict('content.article_source')->allowing('reprint', null);

    expect(Validator::make(['source' => $value], ['source' => ['required', $rule]])->passes())->toBe($passes);
})->with([
    'allowed disabled item' => ['reprint', true],
    'other enabled item' => ['original', true],
    'other value' => ['translated', false],
    'array' => [['reprint'], false],
]);

it('compares the allowed values as strings and applies them to disabled and unknown types too', function (): void {
    $passes = fn (InDict $rule, mixed $value): bool => Validator::make(['source' => $value], ['source' => ['required', $rule]])->passes();

    expect($passes(new InDict('customer_level')->allowing('vip'), 'vip'))->toBeTrue()
        ->and($passes(new InDict('missing')->allowing(7), '7'))->toBeTrue()
        ->and($passes(new InDict('missing')->allowing(7), 'vip'))->toBeFalse();
});

it('returns a new rule from allowing() and keeps the original unchanged', function (): void {
    $rule = new InDict('content.article_source');
    $allowing = $rule->allowing('reprint');

    expect($allowing)->not->toBe($rule)
        ->and(passesInDict('content.article_source', 'reprint'))->toBeFalse()
        ->and(Validator::make(['source' => 'reprint'], ['source' => [$rule]])->passes())->toBeFalse();
});
