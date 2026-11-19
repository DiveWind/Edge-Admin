<?php

use App\Core\System\Models\{Role, User};
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| 后台用户：spatie 的 guard 统一为 web（设计文档 §8.1）
|--------------------------------------------------------------------------
*/

it('finds roles by name while sanctum is the current guard', function (): void {
    $role = Role::factory()->create(['name' => 'editor']);
    Auth::shouldUse('sanctum');

    $user = User::factory()->create()->assignRole('editor');

    expect($user->roles->modelKeys())->toBe([$role->id]);
});
