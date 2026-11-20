<?php

use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 业务规则异常的输出（设计文档 §5.4）：入口自己的格式优先，其余请求由 bootstrap/app.php 的全局兜底返回 422
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Route::post('_test/business-rule', fn (): never => throw new BusinessRuleViolation('Only drafts can be published.'));
    Route::post('_test/business-rule-field', fn (): never => throw new BusinessRuleViolation('The code is taken.', 'code'));
    Route::prefix('api/admin')->middleware('admin')->group(function (): void {
        Route::post('_test/business-rule-field', fn (): never => throw new BusinessRuleViolation('The code is taken.', 'code'));
    });
});

it('returns the standard 422 JSON outside the entries that format it themselves', function (): void {
    $this->postJson('/_test/business-rule')
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'Only drafts can be published.']);

    $this->postJson('/_test/business-rule-field')
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'The code is taken.', 'errors' => ['code' => ['The code is taken.']]]);
});

it('keeps the format of the admin entry (Vben) under its prefix', function (): void {
    $this->postJson('/api/admin/_test/business-rule-field')
        ->assertUnprocessable()
        ->assertExactJson(['code' => 422, 'data' => null, 'message' => 'The code is taken.', 'errors' => ['code' => ['The code is taken.']]]);
});
