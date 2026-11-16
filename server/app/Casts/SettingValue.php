<?php

namespace App\Casts;

use App\Enums\SettingType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * 系统参数 value：按同一条记录的 type 字段读写为 string / int|float / bool / array。
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class SettingValue implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $this->type($attributes)->decode(is_string($value) ? $value : null);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $this->type($attributes)->encode($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function type(array $attributes): SettingType
    {
        $type = $attributes['type'] ?? null;

        return $type instanceof SettingType ? $type : (SettingType::tryFrom((string) $type) ?? SettingType::String);
    }
}
