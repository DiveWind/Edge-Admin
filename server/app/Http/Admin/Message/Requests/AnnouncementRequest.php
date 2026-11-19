<?php

namespace App\Http\Admin\Message\Requests;

use App\Core\Message\Enums\{AnnouncementTarget, NotificationLevel};
use App\Core\Message\Models\Announcement;
use App\Core\System\Models\{Role, User};
use App\Core\System\Rules\OwnedAttachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * 新增、修改公告（POST /message/announcements、PUT /message/announcements/{announcement}，设计文档 §6.9）。
 *
 * 只做输入格式校验：
 * - title（最长 255）、content（HTML，最长 65535 字符，按净化前计；保存时由模型净化）、target（all / roles）必填；
 * - level 可选（新增默认 info，修改时未提交表示不变）；
 * - roles：角色标识数组，target 为 roles 时必填且不能为空，为 all 时不能提交非空值；
 * - attachment_ids：附件 id 数组，每个都必须是操作者自己上传的未绑定附件，或本公告已有的附件（OwnedAttachment）；修改时未提交表示不变。
 * 只有草稿能修改由 AnnouncementPolicy 判定（403）；未声明的字段一律拒绝（failOnUnknownFields）；授权写在控制器的 #[Authorize] 上。
 */
class AnnouncementRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $announcement = $this->route('announcement');
        $actor = $this->user();

        if (! $actor instanceof User) {
            throw new LogicException('AnnouncementRequest 只用于已登录的后台用户。');
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:'.Announcement::CONTENT_MAX_LENGTH],
            'level' => ['sometimes', Rule::enum(NotificationLevel::class)],
            'target' => ['required', Rule::enum(AnnouncementTarget::class)],
            'roles' => ['required_if:target,'.AnnouncementTarget::Roles->value, 'prohibited_unless:target,'.AnnouncementTarget::Roles->value, 'array', 'list'],
            'roles.*' => ['string', 'distinct', Rule::exists(Role::class, 'name')->where('guard_name', User::GUARD)],
            'attachment_ids' => ['sometimes', 'array', 'list'],
            'attachment_ids.*' => ['integer', 'distinct', new OwnedAttachment(
                $actor,
                record: $announcement instanceof Announcement ? $announcement : null,
                collection: Announcement::ATTACHMENT_COLLECTION,
            )],
        ];
    }

    /**
     * 字段名（错误提示中使用）。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('Title'),
            'content' => __('Content'),
            'level' => __('Level'),
            'target' => __('Target'),
            'roles' => __('Roles'),
            'roles.*' => __('Roles'),
            'attachment_ids' => __('Attachments'),
            'attachment_ids.*' => __('Attachments'),
        ];
    }
}
