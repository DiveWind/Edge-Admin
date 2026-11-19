<?php

namespace App\Core\System\Models\Concerns;

use App\Core\System\Actions\DeleteAttachment;
use App\Core\System\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{MorphMany, MorphOne};

/**
 * 绑定了附件的模型（设计文档 §6.8）：附件以多态关联 attachable + 用途名 collection 绑定到记录上。
 *
 * - attachments()：全部附件；attachmentsIn('<用途>')、attachmentIn('<用途>')：某个用途的附件（多个 / 一个）；
 * - 绑定、替换、移除用 Action SyncAttachments，提交的附件 id 用 OwnedAttachment 校验归属；
 * - 记录删除时，它的附件一并删除（连同文件，事务提交后）；软删除的模型在彻底删除时才删。
 * - 已绑定的附件，访问权委托给这条记录的 Policy（AttachmentPolicy::view()）。
 *
 * @mixin Model
 */
trait HasAttachments
{
    /**
     * 记录删除后删除它的附件。
     */
    protected static function bootHasAttachments(): void
    {
        static::deleted(function (Model $record): void {
            if (method_exists($record, 'isForceDeleting') && ! $record->isForceDeleting()) {
                return;
            }

            $delete = resolve(DeleteAttachment::class);

            Attachment::query()
                ->where('attachable_type', $record->getMorphClass())
                ->where('attachable_id', $record->getKey())
                ->get()
                ->each(fn (Attachment $attachment) => $delete->handle($attachment));
        });
    }

    /**
     * 全部附件。
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * 某个用途的附件。
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachmentsIn(string $collection): MorphMany
    {
        return $this->attachments()->where('collection', $collection);
    }

    /**
     * 某个用途的单个附件（如头像）。
     *
     * @return MorphOne<Attachment, $this>
     */
    public function attachmentIn(string $collection): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('collection', $collection);
    }
}
