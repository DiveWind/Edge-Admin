<?php

use App\Http\Middleware\AssignRequestId;
use Illuminate\Support\Facades\{Context, Route};
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| 请求 ID（设计文档 §9）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Route::get('_test/context', fn (): array => ['request_id' => Context::get(AssignRequestId::CONTEXT_KEY)]);
    Route::middleware('admin')->get('api/admin/_test/boom', fn (): never => throw new RuntimeException('boom'));
});

it('adds an X-Request-Id header to every response', function (string $method, string $uri): void {
    $id = $this->json($method, $uri)->headers->get(AssignRequestId::HEADER);

    expect($id)->not->toBeNull()
        ->and(Str::isUuid($id))->toBeTrue();
})->with([
    'admin entry' => ['GET', '/api/admin/ping'],
    'admin entry error' => ['GET', '/api/admin/_test/boom'],
    'unmatched route' => ['GET', '/missing'],
    'health check' => ['GET', '/up'],
]);

it('stores the request ID in the Context', function (): void {
    $response = $this->getJson('/_test/context')->assertOk();

    expect($response->json('request_id'))->toBe($response->headers->get(AssignRequestId::HEADER));
});

it('reuses a valid request ID from upstream', function (): void {
    $this->getJson('/_test/context', [AssignRequestId::HEADER => 'nginx-3f2a9c1b7d'])
        ->assertHeader(AssignRequestId::HEADER, 'nginx-3f2a9c1b7d')
        ->assertJsonPath('request_id', 'nginx-3f2a9c1b7d');
});

it('replaces an invalid request ID from upstream', function (string $id): void {
    $response = $this->getJson('/_test/context', [AssignRequestId::HEADER => $id]);

    expect($response->headers->get(AssignRequestId::HEADER))->not->toBe($id)
        ->and(Str::isUuid((string) $response->headers->get(AssignRequestId::HEADER)))->toBeTrue();
})->with([
    'too short' => 'abc',
    'too long' => str_repeat('a', 129),
    'with spaces' => 'request id 123',
    'with line breaks' => "abcdefgh\nINJECTED",
]);
