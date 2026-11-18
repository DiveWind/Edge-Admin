<?php

use App\Core\System\Models\User;
use App\Http\Admin\Requests\ListRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 列表查询参数（设计文档 §6.3）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Route::middleware('admin')->prefix('api/admin/_test')->group(function (): void {
        Route::get('params', fn (ListRequest $request): array => ['page' => $request->page(), 'pageSize' => $request->pageSize()]);
        Route::get('users', fn (ListRequest $request) => JsonResource::collection(User::query()->orderBy('id')->paginate($request->pageSize())));
    });
});

it('defaults to the first page and the configured page size', function (): void {
    config(['core.apps.admin.pagination.page_size' => 15]);

    $this->getJson('/api/admin/_test/params')->assertOk()->assertJsonPath('data', ['page' => 1, 'pageSize' => 15]);
});

it('reads page and pageSize', function (): void {
    $this->getJson('/api/admin/_test/params?page=3&pageSize=50')->assertOk()->assertJsonPath('data', ['page' => 3, 'pageSize' => 50]);
});

it('rejects a pageSize above the configured maximum', function (): void {
    config(['core.apps.admin.pagination.max_page_size' => 50]);

    $this->getJson('/api/admin/_test/params?pageSize=50')->assertOk();

    $this->getJson('/api/admin/_test/params?pageSize=51')
        ->assertUnprocessable()
        ->assertJsonPath('code', 422)
        ->assertJsonValidationErrors(['pageSize']);
});

it('validates the format of list parameters', function (string $query, string $field): void {
    $this->getJson("/api/admin/_test/params?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'page below 1' => ['page=0', 'page'],
    'page not an integer' => ['page=abc', 'page'],
    'pageSize below 1' => ['pageSize=0', 'pageSize'],
    'sort with invalid characters' => ['sort=name;drop', 'sort'],
    'sort with an empty field' => ['sort=name,,id', 'sort'],
    'filter not an array' => ['filter=active', 'filter'],
]);

it('accepts sort and filter parameters', function (): void {
    $this->getJson('/api/admin/_test/params?sort=-created_at,id&filter[status]=active')->assertOk();
});

it('paginates with pageSize and returns { items, total }', function (): void {
    User::factory()->count(5)->create();

    $this->getJson('/api/admin/_test/users?page=2&pageSize=2')
        ->assertOk()
        ->assertJsonPath('data.total', 5)
        ->assertJsonCount(2, 'data.items');
});
