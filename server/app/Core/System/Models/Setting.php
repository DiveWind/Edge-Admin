<?php

namespace App\Core\System\Models;

use App\Core\System\Enums\SettingType;
use App\Core\System\Observers\SettingObserver;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Database\Factories\Core\System\Models\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 系统参数（多态别名 `setting`，见 SystemServiceProvider），设计文档 §6.7：
 *
 * - key：`<分组>.<参数名>`，唯一；group、type、name、remark、is_public 由模块的声明（SettingRegistry）决定，core:sync-settings 维护；
 * - value：管理员改过的值（JSON 编码）；**null 表示没有改过**，读取时使用声明的默认值（Settings 服务）；
 * - is_builtin：仍在声明中的参数；从声明中移除的转为 false（不删除，代码读不到，也不出现在接口中）。
 *
 * 读取一律通过 Settings 服务（按声明的类型与默认值，带缓存），不直接读本模型。
 * 写入只在 System 模块的 Action 与同步命令中进行（设计文档 §5.2）；写入后（事务提交后）清除参数缓存（SettingObserver）。
 * 没有可批量赋值的字段：全部由 Action 按声明用 forceFill() 写入。
 *
 * @property int $id
 * @property mixed $value 管理员改过的值（json cast：string、int、float、bool 或 array），没有改过时为 null
 */
#[ObservedBy(SettingObserver::class)]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory, UsesAppTimezone;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SettingType::class,
            'value' => 'json',
            'is_public' => 'boolean',
            'is_builtin' => 'boolean',
        ];
    }
}
