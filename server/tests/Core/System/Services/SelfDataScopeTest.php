<?php

use App\Core\System\Models\Concerns\HasDataScope;
use App\Core\System\Models\{Role, User};
use App\Core\System\Services\SelfDataScope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| 数据范围“仅本人”（设计文档 §8.4）：按模型声明的归属列判断，查询与单条判定同一规则
|--------------------------------------------------------------------------
*/

/**
 * 只按默认归属列（created_by）判断的模型：读 users 表，不覆盖 dataScopeOwnerColumns()。
 */
function recordOwnedByCreator(): Model
{
    return new #[Table(name: 'users')] class extends Model
    {
        use HasDataScope;
    };
}

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->own = User::factory()->create(['created_by' => $this->actor->id]);
    $this->other = User::factory()->create();
    $this->roles = collect([Role::factory()->make()]);
});

it('uses created_by by default', function (): void {
    $model = recordOwnedByCreator();
    $query = $model->newQuery();

    new SelfDataScope()->constrain($query, $this->actor, $this->roles);

    expect($query->orderBy('id')->pluck('id')->all())->toBe([$this->own->id])
        ->and(new SelfDataScope()->contains($model->newQuery()->findOrFail($this->own->id), $this->actor, $this->roles))->toBeTrue()
        ->and(new SelfDataScope()->contains($model->newQuery()->findOrFail($this->actor->id), $this->actor, $this->roles))->toBeFalse();
});

it('uses the owner columns declared by the model', function (): void {
    $query = User::query();

    new SelfDataScope()->constrain($query, $this->actor, $this->roles);

    expect($query->orderBy('id')->pluck('id')->all())->toBe([$this->actor->id, $this->own->id])
        ->and(new SelfDataScope()->contains($this->actor, $this->actor, $this->roles))->toBeTrue()
        ->and(new SelfDataScope()->contains($this->own, $this->actor, $this->roles))->toBeTrue()
        ->and(new SelfDataScope()->contains($this->other, $this->actor, $this->roles))->toBeFalse();
});

it('keeps the owner columns inside their own group', function (): void {
    $query = User::query()->whereKey($this->other->id);

    new SelfDataScope()->constrain($query, $this->actor, $this->roles);

    expect($query->exists())->toBeFalse();
});
