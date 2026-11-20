<?php

namespace App\Core\System\Services;

use App\Core\System\Data\{SettingData, SettingGroupData};
use App\Core\System\Enums\SettingType;
use App\Core\System\Models\DictType;
use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;

/**
 * 系统参数分组的声明（Core 的扩展点，设计文档 §5.5、§6.7）：`#[Singleton]`，只保存启动时注册的静态声明，不保存请求数据（§10.3）。
 *
 * 各模块在自己 Provider 的 boot() 中注册本模块的参数分组（一个分组只属于一个模块），
 * 由同步命令 `php artisan core:sync-settings` 写入 settings 表，代码用 Settings 服务读取：
 *
 * ```php
 * public function boot(SettingRegistry $settings): void
 * {
 *     $settings->register(require __DIR__.'/resources/settings.php');
 * }
 * ```
 *
 * 声明格式：`分组 => ['title', 'order', 'settings' => [参数名 => ['type', 'name', 'remark', 'default' | 'config', 'public', 'rules', 'dict']]]`，
 * 参数的键为 `<分组>.<参数名>`。
 *
 * 注册时逐个校验，一组声明全部合法才登记：
 * - 分组：小写标识（`^[a-z][a-z0-9_]*$`），不能重复注册（一个分组只属于一个模块）；`title` 必填，`order` 为整数（默认 0），
 *   `settings` 至少一个参数；
 * - 参数名：小写标识，完整的键 `<分组>.<参数名>` 不超过 128 个字符；
 * - `type`：SettingType 的值；`name` 必填，`remark` 可选；`public` 为布尔（默认 false）；`rules` 为额外校验规则的列表；
 * - 默认值：`default`（类型须与 type 一致）与 `config`（config 键）恰好一个；
 * - `dict`：可选，引用的字典类型编码（DictType::isValidCode()），只用于 string、int（DICT_TYPES）。
 */
#[Singleton]
class SettingRegistry
{
    /**
     * 分组与参数名的格式。
     */
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * 完整键的最大长度（settings.key）。
     */
    public const int KEY_MAX_LENGTH = 128;

    /**
     * 分组的键。
     *
     * @var list<string>
     */
    private const array GROUP_KEYS = ['title', 'order', 'settings'];

    /**
     * 参数的键。
     *
     * @var list<string>
     */
    private const array SETTING_KEYS = ['type', 'name', 'remark', 'default', 'config', 'public', 'rules', 'dict'];

    /**
     * 可以引用字典的参数类型（字典项的 value 是字符串，int 按字符串比较）。
     *
     * @var list<SettingType>
     */
    public const array DICT_TYPES = [SettingType::String, SettingType::Integer];

    /**
     * 按注册顺序的分组。
     *
     * @var array<string, SettingGroupData>
     */
    private array $groups = [];

    /**
     * 注册一组参数分组，通常是 `require` 模块的 resources/settings.php 的结果。
     * 声明有误（分组重复、类型不合法、默认值与类型不符、default 与 config 不是恰好一个等）时抛出异常：这是开发期错误。
     *
     * @throws InvalidArgumentException
     */
    public function register(mixed $groups): void
    {
        if (! is_array($groups)) {
            $this->fail('register()', '声明必须是以分组为键的数组');
        }

        $declared = [];

        foreach ($groups as $key => $group) {
            $key = (string) $key;

            if (preg_match(self::NAME_PATTERN, $key) !== 1) {
                $this->fail($key, '分组必须是小写标识（字母开头，字母、数字、下划线）');
            }

            if (isset($this->groups[$key])) {
                $this->fail($key, '分组重复注册（一个分组只属于一个模块）');
            }

            $declared[$key] = $this->parseGroup($key, $group);
        }

        $this->groups = [...$this->groups, ...$declared];
    }

    /**
     * 全部分组，按 order、注册顺序排列。
     *
     * @return list<SettingGroupData>
     */
    public function groups(): array
    {
        $groups = array_values($this->groups);

        // usort 是稳定排序：order 相同的分组保持注册顺序
        usort($groups, fn (SettingGroupData $a, SettingGroupData $b): int => $a->order <=> $b->order);

        return $groups;
    }

    /**
     * 分组；未声明时为 null。
     */
    public function group(string $key): ?SettingGroupData
    {
        return $this->groups[$key] ?? null;
    }

    /**
     * 全部已声明的参数（按分组顺序、声明顺序），以完整的键为键。
     *
     * @return array<string, SettingData>
     */
    public function all(): array
    {
        $settings = [];

        foreach ($this->groups() as $group) {
            foreach ($group->settings as $setting) {
                $settings[$setting->key] = $setting;
            }
        }

        return $settings;
    }

    /**
     * 参数；未声明时为 null。
     */
    public function get(string $key): ?SettingData
    {
        [$group, $name] = array_pad(explode('.', $key, 2), 2, '');

        return ($this->groups[$group] ?? null)?->settings[$name] ?? null;
    }

    /**
     * 参数是否已声明。
     */
    public function has(string $key): bool
    {
        return $this->get($key) instanceof SettingData;
    }

    /**
     * 校验一个分组的声明。
     *
     * @throws InvalidArgumentException
     */
    private function parseGroup(string $key, mixed $group): SettingGroupData
    {
        if (! is_array($group)) {
            $this->fail($key, '分组必须是数组');
        }

        $this->ensureKnownKeys($key, $group, self::GROUP_KEYS);

        $title = $group['title'] ?? null;
        $order = $group['order'] ?? 0;
        $settings = $group['settings'] ?? null;

        if (! is_string($title) || $title === '') {
            $this->fail($key, 'title 必填（前端 i18n key 或纯文本）');
        }

        if (! is_int($order)) {
            $this->fail($key, 'order 必须是整数');
        }

        if (! is_array($settings) || $settings === []) {
            $this->fail($key, 'settings 必须是以参数名为键的数组，至少一个参数');
        }

        $data = [];

        foreach ($settings as $name => $setting) {
            $data[(string) $name] = $this->parseSetting($key, (string) $name, $setting);
        }

        return new SettingGroupData(key: $key, title: $title, order: $order, settings: $data);
    }

    /**
     * 校验一个参数的声明。
     *
     * @throws InvalidArgumentException
     */
    private function parseSetting(string $group, string $name, mixed $setting): SettingData
    {
        $key = "{$group}.{$name}";

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            $this->fail($key, '参数名必须是小写标识（字母开头，字母、数字、下划线）');
        }

        if (strlen($key) > self::KEY_MAX_LENGTH) {
            $this->fail($key, '键不能超过 '.self::KEY_MAX_LENGTH.' 个字符');
        }

        if (! is_array($setting)) {
            $this->fail($key, '参数必须是数组');
        }

        $this->ensureKnownKeys($key, $setting, self::SETTING_KEYS);

        $type = is_string($setting['type'] ?? null) ? SettingType::tryFrom($setting['type']) : null;
        $title = $setting['name'] ?? null;
        $remark = $setting['remark'] ?? null;
        $public = $setting['public'] ?? false;
        $rules = $setting['rules'] ?? [];
        $config = $setting['config'] ?? null;
        $dict = $setting['dict'] ?? null;

        if ($type === null) {
            $this->fail($key, 'type 必须是 '.implode(' / ', array_column(SettingType::cases(), 'value')));
        }

        if (! is_string($title) || $title === '') {
            $this->fail($key, 'name 必填（前端 i18n key 或纯文本）');
        }

        if ($remark !== null && ! is_string($remark)) {
            $this->fail($key, 'remark 必须是字符串');
        }

        if (! is_bool($public)) {
            $this->fail($key, 'public 必须是布尔值');
        }

        if (! is_array($rules) || ! array_is_list($rules)) {
            $this->fail($key, 'rules 必须是校验规则的列表');
        }

        if (array_key_exists('default', $setting) === array_key_exists('config', $setting)) {
            $this->fail($key, 'default（默认值）与 config（作为默认值来源的 config 键）必须且只能有一个');
        }

        if (array_key_exists('config', $setting) && (! is_string($config) || $config === '')) {
            $this->fail($key, 'config 必须是 config 键，如 core.auth.login_rate_limit');
        }

        if ($dict !== null && (! is_string($dict) || ! DictType::isValidCode($dict))) {
            $this->fail($key, 'dict 必须是字典类型的编码，如 content.article_source');
        }

        if ($dict !== null && ! in_array($type, self::DICT_TYPES, true)) {
            $this->fail($key, "{$type->value} 类型不能引用字典（只用于 string、int）");
        }

        if (array_key_exists('default', $setting) && ! $type->accepts($setting['default'])) {
            $this->fail($key, "default 不是 {$type->value} 类型");
        }

        return new SettingData(
            key: $key,
            group: $group,
            name: $name,
            type: $type,
            title: $title,
            remark: $remark,
            default: $setting['default'] ?? null,
            config: is_string($config) ? $config : null,
            public: $public,
            rules: $rules,
            dict: $dict,
        );
    }

    /**
     * @param  array<array-key, mixed>  $declaration
     * @param  list<string>  $allowed
     *
     * @throws InvalidArgumentException
     */
    private function ensureKnownKeys(string $where, array $declaration, array $allowed): void
    {
        if (($unknown = array_diff(array_map(strval(...), array_keys($declaration)), $allowed)) !== []) {
            $this->fail($where, '未知的键 '.implode('、', $unknown).'（允许 '.implode('、', $allowed).'）');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function fail(string $where, string $message): never
    {
        throw new InvalidArgumentException("系统参数声明错误（{$where}）：{$message}。");
    }
}
