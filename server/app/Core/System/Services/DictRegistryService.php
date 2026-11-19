<?php

namespace App\Core\System\Services;

use App\Core\System\Contracts\DictRegistry;
use App\Core\System\Data\{DictItemData, DictTypeData};
use App\Core\System\Models\{DictItem, DictType};
use InvalidArgumentException;

/**
 * DictRegistry 的实现（单例，SystemServiceProvider 的 $singletons）：只保存启动时注册的静态声明，不保存请求数据。
 *
 * 注册时逐个校验，一组声明全部合法才登记：
 * - code：小写点分、以字母开头，最长 64（DictType::isValidCode()，与管理接口同一规则），全局唯一；
 * - name：必填字符串；remark：可选字符串；
 * - items：列表，每项 `value`（非空字符串，最长 64，类型内唯一）、`label`（非空字符串）、
 *   `color`（可选，DictItem::COLOR_PATTERN）、`sort`（可选整数，默认为声明中的序号）。
 */
class DictRegistryService implements DictRegistry
{
    /**
     * 类型的键。
     *
     * @var list<string>
     */
    private const array TYPE_KEYS = ['code', 'name', 'remark', 'items'];

    /**
     * 字典项的键。
     *
     * @var list<string>
     */
    private const array ITEM_KEYS = ['value', 'label', 'color', 'sort'];

    /**
     * @var array<string, DictTypeData>
     */
    private array $types = [];

    public function register(mixed $types): void
    {
        if (! is_array($types) || ! array_is_list($types)) {
            $this->fail('register()', '声明必须是字典类型的列表');
        }

        $declared = [];

        foreach ($types as $index => $type) {
            $data = $this->type($type, "第 {$index} 个类型");

            if (isset($this->types[$data->code]) || isset($declared[$data->code])) {
                $this->fail($data->code, '类型编码重复声明');
            }

            $declared[$data->code] = $data;
        }

        $this->types = [...$this->types, ...$declared];
    }

    public function all(): array
    {
        return $this->types;
    }

    public function has(string $code): bool
    {
        return isset($this->types[$code]);
    }

    /**
     * 校验一个类型的声明。
     *
     * @throws InvalidArgumentException
     */
    private function type(mixed $type, string $where): DictTypeData
    {
        if (! is_array($type)) {
            $this->fail($where, '类型必须是数组');
        }

        $code = $type['code'] ?? null;

        if (! is_string($code) || ! DictType::isValidCode($code)) {
            $this->fail($where, 'code 必须是小写点分、以字母开头的标识（最长 '.DictType::CODE_MAX_LENGTH.'），如 content.article_source');
        }

        $this->ensureKnownKeys($code, $type, self::TYPE_KEYS);

        $name = $type['name'] ?? null;
        $remark = $type['remark'] ?? null;
        $items = $type['items'] ?? [];

        if (! is_string($name) || $name === '') {
            $this->fail($code, 'name 必填（前端 i18n key 或纯文本）');
        }

        if ($remark !== null && ! is_string($remark)) {
            $this->fail($code, 'remark 必须是字符串');
        }

        if (! is_array($items) || ! array_is_list($items)) {
            $this->fail($code, 'items 必须是字典项的列表');
        }

        $data = [];

        foreach ($items as $index => $item) {
            $itemData = $this->item($code, $item, $index);

            if (isset($data[$itemData->value])) {
                $this->fail($code, "字典项的 value {$itemData->value} 重复");
            }

            $data[$itemData->value] = $itemData;
        }

        return new DictTypeData(code: $code, name: $name, remark: $remark, items: $data);
    }

    /**
     * 校验一个字典项的声明。
     *
     * @throws InvalidArgumentException
     */
    private function item(string $code, mixed $item, int $index): DictItemData
    {
        $where = "{$code} 的第 {$index} 个字典项";

        if (! is_array($item)) {
            $this->fail($where, '字典项必须是数组');
        }

        $this->ensureKnownKeys($where, $item, self::ITEM_KEYS);

        $value = $item['value'] ?? null;
        $label = $item['label'] ?? null;
        $color = $item['color'] ?? null;
        $sort = $item['sort'] ?? $index;

        if (! is_string($value) || $value === '' || mb_strlen($value) > DictItem::VALUE_MAX_LENGTH) {
            $this->fail($where, 'value 必须是非空字符串（最长 '.DictItem::VALUE_MAX_LENGTH.'）');
        }

        if (! is_string($label) || $label === '') {
            $this->fail($where, 'label 必填（前端 i18n key 或纯文本）');
        }

        if ($color !== null && (! is_string($color) || preg_match(DictItem::COLOR_PATTERN, $color) !== 1)) {
            $this->fail($where, 'color 必须是 antd 的预设色名（如 success）或 #RGB / #RRGGBB');
        }

        if (! is_int($sort)) {
            $this->fail($where, 'sort 必须是整数');
        }

        return new DictItemData(value: $value, label: $label, color: $color, sort: $sort);
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
        throw new InvalidArgumentException("字典声明错误（{$where}）：{$message}。");
    }
}
