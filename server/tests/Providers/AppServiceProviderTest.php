<?php

use App\Core\System\Models\User;
use App\Http\Admin\Requests\ListRequest;
use Carbon\CarbonImmutable;
use Database\Factories\Core\System\Models\UserFactory;
use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\{Route, Validator};
use Illuminate\Validation\Rules\Password;

/*
|--------------------------------------------------------------------------
| 全局默认（设计文档 §4.5、§9、§10）
|--------------------------------------------------------------------------
*/

it('enforces the morph map', function (): void {
    expect((new User)->getMorphClass())->toBe('user')
        ->and(Relation::getMorphedModel('user'))->toBe(User::class);

    $unmapped = new class extends Model {};

    expect(fn () => $unmapped->getMorphClass())->toThrow(ClassMorphViolationException::class);
});

it('guesses models from factories in the module layout', function (): void {
    expect(UserFactory::new()->modelName())->toBe(User::class)
        ->and(User::factory()->make())->toBeInstanceOf(User::class);
});

it('enables strict models outside production', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

it('uses immutable dates', function (): void {
    expect(now())->toBeInstanceOf(CarbonImmutable::class)
        ->and(User::factory()->make()->email_verified_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('rejects unknown fields in form requests', function (): void {
    Route::middleware('admin')->post('api/admin/_test/form', fn (ListRequest $request) => $request->validated());

    $this->postJson('/api/admin/_test/form', ['page' => 1, 'role' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role'])
        ->assertJsonMissingValidationErrors(['page']);
});

it('applies the default password rules', function (): void {
    expect(Validator::make(['password' => 'short'], ['password' => Password::defaults()])->fails())->toBeTrue()
        ->and(Validator::make(['password' => 'long enough'], ['password' => Password::defaults()])->passes())->toBeTrue();
});

it('requires strong passwords in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(Validator::make(['password' => 'long enough'], ['password' => Password::defaults()])->fails())->toBeTrue()
        ->and(Validator::make(['password' => 'Long-enough-1'], ['password' => Password::defaults()])->passes())->toBeTrue();
});
