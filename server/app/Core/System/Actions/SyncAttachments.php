<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\Attachment;
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Support\Facades\{DB, Lang};
use Illuminate\Validation\ValidationException;

/**
 * 把附件绑定到记录的某个用途上（设计文档 §6.8）：业务的 Action 在保存记录后调用，提交的附件 id 先用 OwnedAttachment 校验归属。
 *
 * ```php
 * resolve(SyncAttachments::class)->handle($article, 'files', $validated['files'] ?? [], 'files');
 * ```
 *
 * - 绑定：$ids 中的附件写入 attachable（多态别名，§4.5）与 collection；
 * - 替换、移除：该用途下原有、但不在 $ids 中的附件删除（连同文件，事务提交后，DeleteAttachment）；
 * - 并发：在一个事务中先对提交的附件 lockForUpdate()，再复核“未绑定，或已绑定在这里”；被别的记录抢先绑定、不存在 → 422（$attribute），
 *   与 OwnedAttachment 的提示相同，不区分原因。
 *
 * 返回该用途绑定后的附件（按 $ids 的顺序）。
 */
class SyncAttachments
{
    public function __construct(private readonly DeleteAttachment $deleteAttachment) {}

    /**
     * Execute the action.
     *
     * @param  array<array-key, int|string>  $ids  附件 id（已校验归属）
     * @param  string  $attribute  校验错误挂的字段
     * @return Collection<int, Attachment>
     *
     * @throws ValidationException
     */
    public function handle(Model $record, string $collection, array $ids, string $attribute = 'attachments'): Collection
    {
        $ids = array_values(array_unique(array_map(intval(...), $ids)));

        return DB::transaction(function () use ($record, $collection, $ids, $attribute): Collection {
            $attachments = Attachment::query()->whereKey($ids)->lockForUpdate()->get()->keyBy('id');

            $usable = $attachments->count() === count($ids)
                && $attachments->every(fn (Attachment $attachment): bool => ! $attachment->isBound() || $attachment->isBoundTo($record, $collection));

            if (! $usable) {
                throw ValidationException::withMessages([$attribute => [__('validation.exists', ['attribute' => $this->attributeName($attribute)])]]);
            }

            Attachment::query()->boundTo($record, $collection)->whereKeyNot($ids)->get()
                ->each(fn (Attachment $attachment) => $this->deleteAttachment->handle($attachment));

            foreach ($attachments as $attachment) {
                if (! $attachment->isBound()) {
                    $attachment->attachable()->associate($record);
                    $attachment->collection = $collection;
                    $attachment->save();
                }
            }

            return $attachments->sortBy(fn (Attachment $attachment): int => (int) array_search($attachment->id, $ids, true))->values();
        });
    }

    /**
     * 错误提示中的字段名：语言包 validation.attributes 中有就用它，否则与校验器相同，把下划线换成空格。
     */
    private function attributeName(string $attribute): string
    {
        return Lang::has("validation.attributes.{$attribute}") ? __("validation.attributes.{$attribute}") : str_replace('_', ' ', $attribute);
    }
}
