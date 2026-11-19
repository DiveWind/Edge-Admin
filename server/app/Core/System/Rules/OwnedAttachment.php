<?php

namespace App\Core\System\Rules;

use App\Core\System\Models\{Attachment, User};
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 业务提交的附件 id 必须是操作者能使用的附件（设计文档 §6.8、§9 的“引用附件时校验归属”）：
 *
 * ```php
 * 'cover_id' => ['nullable', 'integer', new OwnedAttachment($actor, record: $article, collection: 'cover', mimeTypes: Attachment::PREVIEWABLE_MIME_TYPES)],
 * 'files.*' => ['integer', new OwnedAttachment($actor, record: $article, collection: 'files')],
 * ```
 *
 * 通过的条件（任一）：
 * 1. 操作者自己上传的、尚未绑定的附件；
 * 2. 已经绑定在 $record 的 $collection 用途上的附件（编辑表单时原样提交现有附件；新增记录时 $record 为 null，只能用第 1 条）。
 *
 * 给了 $mimeTypes 时附件的 MIME 还必须在其中。不通过一律为 validation.exists：不区分“不存在”“不是你的”“已被别处使用”，
 * 不暴露别人的附件 id。已绑定在其他记录上的附件不能引用（绑定是一对一的，引用就等于把它挪走）。
 * 绑定本身用 Action SyncAttachments（它在事务中加锁复核，防止并发时两条记录抢同一个附件）。
 */
class OwnedAttachment implements ValidationRule
{
    /**
     * @param  User  $actor  操作者
     * @param  ?Model  $record  要绑定到的记录（新增时为 null）
     * @param  ?string  $collection  绑定的用途名
     * @param  list<string>  $mimeTypes  允许的 MIME（空数组表示不限）
     */
    public function __construct(
        private readonly User $actor,
        private readonly ?Model $record = null,
        private readonly ?string $collection = null,
        private readonly array $mimeTypes = [],
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $attachment = is_int($value) || (is_string($value) && ctype_digit($value))
            ? Attachment::query()->find((int) $value)
            : null;

        if ($attachment === null || ! $this->usable($attachment)) {
            $fail('validation.exists')->translate();

            return;
        }

        if ($this->mimeTypes !== [] && ! in_array($attachment->mime_type, $this->mimeTypes, true)) {
            $fail('validation.mimetypes')->translate(['values' => implode(', ', $this->mimeTypes)]);
        }
    }

    /**
     * 自己上传且未绑定，或已绑定在这条记录的这个用途上。
     */
    private function usable(Attachment $attachment): bool
    {
        if (! $attachment->isBound()) {
            return $attachment->created_by === $this->actor->getKey();
        }

        return $this->record instanceof Model && $this->record->exists && $attachment->isBoundTo($this->record, $this->collection);
    }
}
