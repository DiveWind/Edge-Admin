<?php

use App\Core\System\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 后台入口的响应包装（设计文档 §6.1）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Route::middleware('admin')->prefix('api/admin/_test')->group(function (): void {
        Route::get('array', fn (): array => ['name' => 'Alice']);
        Route::get('list', fn (): array => [1, 2, 3]);
        Route::get('null', fn () => response()->json(null));
        Route::get('resource', fn (): JsonResource => new JsonResource(['id' => 1]));
        Route::get('resource-additional', fn () => new JsonResource(['id' => 1])->additional(['extra' => true]));
        Route::get('collection', fn () => JsonResource::collection([['id' => 1], ['id' => 2]]));
        Route::get('paginator', fn () => User::query()->orderBy('id')->paginate(2));
        Route::get('paginated-resource', fn () => JsonResource::collection(User::query()->orderBy('id')->paginate(2)));
        Route::post('created', fn () => response()->json(['id' => 1], 201));
        Route::post('no-content', fn () => response()->noContent());
        Route::post('empty-json-204', fn (): JsonResponse => new JsonResponse('', 204));
        Route::post('empty-json-201', fn (): JsonResponse => new JsonResponse('', 201));
        Route::post('empty-json-200', fn (): JsonResponse => new JsonResponse('', 200));
        Route::get('headers', fn () => response()->json(['ok' => true])->header('X-Custom', 'yes')->withCookie(cookie('flavour', 'oat')));
        Route::post('no-content-headers', fn () => response()->noContent()->header('X-Custom', 'yes'));
        Route::get('error-json', fn () => response()->json(['message' => 'Account locked', 'reason' => 'too many attempts'], 423));
        Route::get('error-empty', fn () => response()->noContent(410));
        Route::get('text', fn (): string => 'plain text');
        Route::get('download', fn () => response()->download(__FILE__, 'test.php'));
        Route::get('redirect', fn (): Redirector|\Illuminate\Http\RedirectResponse => redirect('/'));
    });
});

it('wraps successful JSON responses', function (string $uri, mixed $data): void {
    $this->getJson("/api/admin/_test/{$uri}")
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => $data, 'message' => 'ok']);
})->with([
    'array' => ['array', ['name' => 'Alice']],
    'list' => ['list', [1, 2, 3]],
    'null' => ['null', []],
    'resource without the data wrapper' => ['resource', ['id' => 1]],
    'resource with additional data' => ['resource-additional', ['data' => ['id' => 1], 'extra' => true]],
    'resource collection' => ['collection', [['id' => 1], ['id' => 2]]],
]);

it('returns paginated lists as { items, total }', function (string $uri): void {
    $users = User::factory()->count(3)->create();

    $response = $this->getJson("/api/admin/_test/{$uri}?page=2")->assertOk();

    expect($response->json())->toHaveKeys(['code', 'data', 'message'])
        ->and($response->json('code'))->toBe(0)
        ->and($response->json('data.total'))->toBe(3)
        ->and(array_keys($response->json('data')))->toBe(['items', 'total'])
        ->and(array_column($response->json('data.items'), 'id'))->toBe([$users[2]->id]);
})->with(['paginator', 'paginated-resource']);

it('keeps the status code of successful responses', function (): void {
    $this->postJson('/api/admin/_test/created')
        ->assertCreated()
        ->assertExactJson(['code' => 0, 'data' => ['id' => 1], 'message' => 'ok']);
});

it('never returns 204: empty successful responses become 200 with data null', function (string $uri): void {
    $response = $this->postJson("/api/admin/_test/{$uri}")
        ->assertOk()
        ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

    expect($response->headers->get('Content-Type'))->toBe('application/json');
})->with(['no-content', 'empty-json-204', 'empty-json-200']);

it('keeps other status codes of empty successful responses', function (): void {
    $this->postJson('/api/admin/_test/empty-json-201')
        ->assertCreated()
        ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);
});

it('keeps response headers and cookies', function (): void {
    $this->getJson('/api/admin/_test/headers')
        ->assertOk()
        ->assertHeader('X-Custom', 'yes')
        ->assertHeader('Content-Type', 'application/json')
        ->assertPlainCookie('flavour', 'oat')
        ->assertExactJson(['code' => 0, 'data' => ['ok' => true], 'message' => 'ok']);

    $this->postJson('/api/admin/_test/no-content-headers')
        ->assertOk()
        ->assertHeader('X-Custom', 'yes')
        ->assertHeader('Content-Type', 'application/json');
});

it('wraps error responses returned by controllers', function (): void {
    $this->getJson('/api/admin/_test/error-json')
        ->assertStatus(423)
        ->assertExactJson(['code' => 423, 'data' => null, 'message' => 'Account locked', 'reason' => 'too many attempts']);

    $this->getJson('/api/admin/_test/error-empty')
        ->assertStatus(410)
        ->assertExactJson(['code' => 410, 'data' => null, 'message' => 'Gone']);
});

it('leaves non-JSON responses with content untouched', function (): void {
    $this->get('/api/admin/_test/text')->assertOk()->assertContent('plain text');
    $this->get('/api/admin/_test/download')->assertOk()->assertDownload('test.php');
    $this->get('/api/admin/_test/redirect')->assertRedirect('/');
});
