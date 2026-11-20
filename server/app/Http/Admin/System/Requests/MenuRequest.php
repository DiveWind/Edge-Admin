<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\{PermissionType, Status};
use App\Core\System\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 新增、修改节点（POST /system/menus、PUT /system/menus/{menu}，设计文档 §6.5、§8.2）。
 *
 * 只做输入格式校验，按 type 决定各字段必填还是不允许提交（与类型不符的字段一律拒绝，不静默清空；null 视为未提交）：
 *
 * | type | path | component | meta.iframeSrc | meta.link | 其余 meta 键 |
 * |---|---|---|---|---|---|
 * | catalog | 必填 | 不允许 | 不允许 | 不允许 | 可选 |
 * | menu | 必填 | 必填 | 不允许 | 不允许 | 可选 |
 * | embedded | 必填 | 不允许 | 必填 | 不允许 | 可选 |
 * | link | 必填 | 不允许 | 不允许 | 必填 | 可选 |
 * | button | 不允许 | 不允许 | 不允许 | 不允许 | 不允许（只有 title、icon、order） |
 *
 * - name：权限码，必填、小写点分、以字母开头（Permission::NAME_PATTERN，与模块声明同一规则），全局唯一；
 * - path 以 / 开头，非按钮节点之间唯一；component 以 / 开头（相对 src/views）；iframeSrc、link 只允许 http / https 网址；
 * - meta.title 必填（前端 i18n key 或纯文本）；meta.icon、meta.order 可选；meta 中未知的键 → 422（`meta.<键>`）；
 * - parent_id、status 可选：新增时默认根节点、启用；修改时未提交表示不变。meta 整体替换：未提交的 icon 为 null、order 为 0，
 *   其余键去掉；值为 null 的键视为未设置。
 *
 * 类型层级、成环、内置节点的结构性字段、生命线节点等业务规则在 System 模块的 Action 中（CreatePermission、UpdatePermission）。
 * 未声明的字段一律拒绝（AppServiceProvider：failOnUnknownFields）；授权写在控制器的 #[Authorize] 上。
 */
class MenuRequest extends FormRequest
{
    /**
     * meta 列中各键的格式（Permission::META_KEYS；iframeSrc、link 另按类型处理）。
     *
     * @var array<string, list<string>>
     */
    private const array META_RULES = [
        'activePath' => ['string', 'max:255', 'starts_with:/'],
        'activeIcon' => ['string', 'max:255'],
        'keepAlive' => ['boolean'],
        'affixTab' => ['boolean'],
        'hideInMenu' => ['boolean'],
        'hideChildrenInMenu' => ['boolean'],
        'hideInBreadcrumb' => ['boolean'],
        'hideInTab' => ['boolean'],
        'badge' => ['string', 'max:255'],
        'badgeType' => ['string', 'in:dot,normal'],
        'badgeVariants' => ['string', 'max:255'],
        'iframeSrc' => ['string', 'max:2048', 'url:http,https'],
        'link' => ['string', 'max:2048', 'url:http,https'],
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $menu = $this->route('menu');
        $ignore = $menu instanceof Permission ? $menu : null;
        $type = $this->type();

        $rules = [
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Permission::class, 'id')],
            'type' => ['required', Rule::enum(PermissionType::class)],
            'name' => ['required', 'string', 'max:255', 'regex:'.Permission::NAME_PATTERN, Rule::unique(Permission::class, 'name')->ignore($ignore)],
            'path' => match ($type?->isRoute()) {
                null => ['nullable', 'string', 'max:255', 'starts_with:/'],
                true => ['required', 'string', 'max:255', 'starts_with:/', Rule::unique(Permission::class, 'path')->ignore($ignore)],
                false => ['prohibited'],
            },
            'component' => match ($type?->usesComponent()) {
                null => ['nullable', 'string', 'max:255', 'starts_with:/'],
                true => ['required', 'string', 'max:255', 'starts_with:/'],
                false => ['prohibited'],
            },
            'status' => ['sometimes', Rule::enum(Status::class)],
            'meta' => ['required', 'array'],
            'meta.title' => ['required', 'string', 'max:255'],
            'meta.icon' => ['nullable', 'string', 'max:255'],
            'meta.order' => ['nullable', 'integer', 'min:-2147483648', 'max:2147483647'],
        ];

        foreach (self::META_RULES as $key => $format) {
            $rules["meta.{$key}"] = match (true) {
                ! $type instanceof PermissionType => ['nullable', ...$format],
                $type->requiredMetaKey() === $key => ['required', ...$format],
                $type->allowsMetaKey($key) => ['nullable', ...$format],
                default => ['prohibited'],
            };
        }

        return $rules;
    }

    /**
     * 字段名（错误提示中使用）。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => __('Parent node'),
            'type' => __('Type'),
            'name' => __('Permission code'),
            'path' => __('Path'),
            'component' => __('Component'),
            'status' => __('Status'),
            'meta.title' => __('Title'),
            'meta.icon' => __('Icon'),
            'meta.order' => __('Sort order'),
            'meta.iframeSrc' => __('Embedded page URL'),
            'meta.link' => __('Link URL'),
        ];
    }

    /**
     * 自定义提示。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('The permission code must be lower-case segments separated by dots and start with a letter, such as system.user.'),
        ];
    }

    /**
     * 节点的字段（表结构的列），交给 CreatePermission / UpdatePermission：meta.title / meta.icon / meta.order 转为 title / icon / sort，
     * 其余 meta 键存入 meta 列（去掉 null，布尔键转为布尔值）；parent_id、status 只在提交时出现。
     *
     * @return array<string, mixed>
     */
    public function menuAttributes(): array
    {
        $validated = $this->validated();

        /** @var array<string, mixed> $meta */
        $meta = $validated['meta'];
        $extra = [];

        foreach (array_intersect_key($meta, self::META_RULES) as $key => $value) {
            if ($value !== null) {
                $extra[$key] = in_array('boolean', self::META_RULES[$key], true) ? (bool) $value : $value;
            }
        }

        return [
            ...array_intersect_key($validated, array_flip(['parent_id', 'status'])),
            'type' => $validated['type'],
            'name' => $validated['name'],
            'path' => $validated['path'] ?? null,
            'component' => $validated['component'] ?? null,
            'title' => $meta['title'],
            'icon' => $meta['icon'] ?? null,
            'sort' => $meta['order'] ?? 0,
            'meta' => $extra === [] ? null : $extra,
        ];
    }

    /**
     * 提交的类型；未提交或不合法时为 null（只校验通用格式，由 type 字段的错误提示用户）。
     */
    private function type(): ?PermissionType
    {
        $type = $this->input('type');

        return is_string($type) ? PermissionType::tryFrom($type) : null;
    }
}
