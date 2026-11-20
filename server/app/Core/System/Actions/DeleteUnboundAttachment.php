<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\Attachment;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * 删除未绑定的附件（设计文档 §6.8）：附件管理的删除（DELETE /system/attachments/{attachment}）与清理（model:prune，Attachment::prune()）。
 *
 * “是否未绑定”在锁内复核：在事务中 lockForUpdate() 重新读取这一行，仍未绑定才删除（连同文件，事务提交后，DeleteAttachment）。
 * 绑定（SyncAttachments）同样先锁附件行，因此“判断未绑定之后、删除之前被绑定”的附件不会被删掉：
 *
 * - 附件管理（$skipIfBound = false）：已被绑定 → 422（message，并发时状态已变，§6.1）；锁外的 403 由 AttachmentPolicy 先判定；
 * - 附件管理时已被别人删除 → 404；
 * - 清理（$skipIfBound = true）：已被绑定或已不存在 → 跳过，返回 false。
 *
 * 返回是否删除了附件。
 */
class DeleteUnboundAttachment
{
    public function __construct(private readonly DeleteAttachment $deleteAttachment) {}

    /**
     * Execute the action.
     *
     * @throws BusinessRuleViolation
     * @throws ModelNotFoundException<Attachment>
     */
    public function handle(Attachment $attachment, bool $skipIfBound = false): bool
    {
        return DB::transaction(function () use ($attachment, $skipIfBound): bool {
            $query = Attachment::query()->whereKey($attachment->getKey())->lockForUpdate();
            $locked = $skipIfBound ? $query->first() : $query->firstOrFail();

            if ($locked === null || $locked->isBound()) {
                return $skipIfBound ? false : throw new BusinessRuleViolation(__('Attachments in use cannot be deleted.'));
            }

            $this->deleteAttachment->handle($locked);

            return true;
        });
    }
}
