<?php

use App\Core\System\Models\{DictItem, DictType, Setting};
use App\Core\System\Services\Settings;

/*
|--------------------------------------------------------------------------
| 系统参数：GET /system/settings 与 PUT /system/settings/{setting}（设计文档 §6.7、§8.6）
|--------------------------------------------------------------------------
|
| 测试用的分组 demo 见 tests/Pest.php 的 demoSettings()（每种类型一个参数）。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('lists the groups declared by the System module', function (): void {
    $this->actingAs(superAdminActor())
        ->getJson('/api/admin/system/settings')
        ->assertOk()
        ->assertJsonPath('data.*.group', ['site', 'auth'])
        ->assertJsonPath('data.1.settings.0.key', 'auth.login_rate_limit');
});

describe('index', function (): void {
    beforeEach(function (): void {
        declareSettings(demoSettings());
    });

    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/system/settings')->assertUnauthorized();
    });

    it('returns 403 without the system.setting code', function (): void {
        $this->actingAs(userWithPermissions('system.dict'))
            ->getJson('/api/admin/system/settings')
            ->assertForbidden();
    });

    it('lists the declared settings by group with their current and default values', function (): void {
        config(['core.auth.login_rate_limit' => 5]);
        Setting::factory()->create(['key' => 'demo.limit', 'group' => 'demo', 'value' => 20]);

        $response = $this->actingAs(userWithPermissions('system.setting'))
            ->getJson('/api/admin/system/settings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group', 'demo')
            ->assertJsonPath('data.0.title', 'demo.title')
            ->assertJsonPath('data.0.settings.*.key', ['demo.name', 'demo.notice', 'demo.limit', 'demo.ratio', 'demo.enabled', 'demo.options']);

        expect($response->json('data.0.settings.0'))->toBe([
            'key' => 'demo.name', 'name' => 'demo.name', 'remark' => null, 'type' => 'string',
            'value' => 'Demo', 'default' => 'Demo', 'is_customized' => false, 'is_public' => true, 'dict' => null,
        ])->and($response->json('data.0.settings.2'))->toMatchArray(['type' => 'int', 'value' => 20, 'default' => 5, 'is_customized' => true]);
    });
});

describe('update', function (): void {
    beforeEach(function (): void {
        declareSettings(demoSettings());
    });

    it('returns 401 for guests', function (): void {
        $this->putJson('/api/admin/system/settings/demo.limit', ['value' => 10])->assertUnauthorized();
    });

    it('returns 403 without the system.setting.update code', function (): void {
        $this->actingAs(userWithPermissions('system.setting'))
            ->putJson('/api/admin/system/settings/demo.limit', ['value' => 10])
            ->assertForbidden();

        $this->assertDatabaseCount('settings', 0);
    });

    it('saves the value converted to the declared type', function (string $key, mixed $input, mixed $stored): void {
        $this->actingAs(userWithPermissions('system.setting.update'))
            ->putJson("/api/admin/system/settings/{$key}", ['value' => $input])
            ->assertOk()
            ->assertJsonPath('data.key', $key)
            ->assertJsonPath('data.value', fn (mixed $value): bool => $value == $stored)
            ->assertJsonPath('data.is_customized', true);

        expect(resolve(Settings::class)->get($key))->toBe($stored);
    })->with([
        'string' => ['demo.name', 'Acme', 'Acme'],
        'int from a numeric string' => ['demo.limit', '10', 10],
        'float' => ['demo.ratio', 1.25, 1.25],
        'float from an integer' => ['demo.ratio', 2, 2.0],
        'bool true' => ['demo.enabled', true, true],
        'bool from "1"' => ['demo.enabled', '1', true],
        'bool from 0' => ['demo.enabled', 0, false],
        'json object' => ['demo.options', ['theme' => ['color' => 'blue'], 'tags' => ['a', 'b']], ['theme' => ['color' => 'blue'], 'tags' => ['a', 'b']]],
        'json list' => ['demo.options', [1, 2], [1, 2]],
    ]);

    it('restores the default value for null and empty strings', function (mixed $input): void {
        Setting::factory()->create(['key' => 'demo.name', 'group' => 'demo', 'value' => 'Acme']);

        $this->actingAs(superAdminActor())
            ->putJson('/api/admin/system/settings/demo.name', ['value' => $input])
            ->assertOk()
            ->assertJsonPath('data.value', 'Demo')
            ->assertJsonPath('data.is_customized', false);

        expect(Setting::query()->where('key', 'demo.name')->sole()->value)->toBeNull();
    })->with(['null' => [null], 'empty string' => ['']]);

    it('validates the value by its type and the declared rules', function (string $key, mixed $input): void {
        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/settings/{$key}", ['value' => $input])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);

        $this->assertDatabaseCount('settings', 0);
    })->with([
        'int from text' => ['demo.limit', 'ten'],
        'int from a decimal' => ['demo.limit', 1.5],
        'int below the declared min' => ['demo.limit', 0],
        'int above the declared max' => ['demo.limit', 101],
        'bool from text' => ['demo.enabled', 'yes'],
        'json from a string' => ['demo.options', '{"a":1}'],
        'string from an array' => ['demo.name', ['Acme']],
        'string too long' => ['demo.name', str_repeat('a', 256)],
        'float from text' => ['demo.ratio', 'half'],
    ]);

    it('requires the value and rejects other fields', function (array $payload, string $field): void {
        $this->actingAs(superAdminActor())
            ->putJson('/api/admin/system/settings/demo.name', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'missing value' => [[], 'value'],
        'other field' => [['value' => 'Acme', 'type' => 'int'], 'type'],
    ]);

    it('accepts only enabled items of the referenced dictionary and lists the dictionary', function (): void {
        $groups = demoSettings();
        $groups['demo']['settings']['level'] = ['type' => 'string', 'name' => 'demo.level', 'default' => 'basic', 'dict' => 'demo.level'];
        $groups['demo']['settings']['tier'] = ['type' => 'int', 'name' => 'demo.tier', 'default' => 1, 'dict' => 'demo.tier'];
        declareSettings($groups);
        $level = DictType::factory()->create(['code' => 'demo.level']);
        DictItem::factory()->for($level, 'type')->create(['value' => 'gold']);
        DictItem::factory()->for($level, 'type')->disabled()->create(['value' => 'silver']);
        $tier = DictType::factory()->create(['code' => 'demo.tier']);
        DictItem::factory()->for($tier, 'type')->create(['value' => '2']);
        $actor = superAdminActor();

        $this->actingAs($actor)->putJson('/api/admin/system/settings/demo.level', ['value' => 'gold'])
            ->assertOk()
            ->assertJsonPath('data.value', 'gold')
            ->assertJsonPath('data.dict', 'demo.level');
        $this->actingAs($actor)->putJson('/api/admin/system/settings/demo.tier', ['value' => 2])->assertOk()->assertJsonPath('data.value', 2);

        foreach (['silver', 'platinum'] as $value) {
            $this->actingAs($actor)->putJson('/api/admin/system/settings/demo.level', ['value' => $value])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['value']);
        }

        $this->actingAs($actor)->putJson('/api/admin/system/settings/demo.tier', ['value' => 3])->assertUnprocessable();
        $this->actingAs($actor)->getJson('/api/admin/system/settings')->assertJsonPath('data.0.settings.6.dict', 'demo.level');
    });

    it('rejects every value while the referenced dictionary does not exist', function (): void {
        $groups = demoSettings();
        $groups['demo']['settings']['level'] = ['type' => 'string', 'name' => 'demo.level', 'default' => 'basic', 'dict' => 'demo.level'];
        declareSettings($groups);

        $this->actingAs(superAdminActor())
            ->putJson('/api/admin/system/settings/demo.level', ['value' => 'gold'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    });

    it('returns 404 for keys that are not declared', function (string $key): void {
        Setting::factory()->create(['key' => 'demo.released', 'group' => 'demo', 'is_builtin' => false]);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/system/settings/{$key}", ['value' => 'x'])
            ->assertNotFound();
    })->with(['unknown' => 'demo.missing', 'released' => 'demo.released', 'group only' => 'demo', 'malformed' => 'Demo.Name']);
});
