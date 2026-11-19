<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\Attachment;
use App\Http\Admin\AdminServiceProvider;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use LogicException;

/**
 * 附件的输出格式（Admin 入口，设计文档 §6.8）。
 *
 * - name：原始文件名；mime_type、extension：服务端探测的结果；is_bound 与 attachable_type（多态别名）、attachable_id、collection：绑定对象；
 * - creator：上传者 `{ id, username, name }`（加载了 creator 关联时输出，已删除时为 null）；
 * - download_url、preview_url：本入口的签名临时 URL（有效期 config('core.attachments.url_ttl') 分钟，到 url_expires_at 为止），
 *   过期时间对齐到固定窗口（urlExpiresAt()），同一窗口内地址不变、可被浏览器缓存；不需要登录；preview_url 只有可预览的图片才有，否则为 null。只在已经通过授权的接口中签发（上传、附件详情、附件管理、业务记录自己的接口）；
 * - 不输出 disk、path（存储的内部细节）；时间为带时区偏移的 ISO 8601。
 *
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expiresAt = self::urlExpiresAt();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'sha256' => $this->sha256,
            'is_bound' => $this->isBound(),
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'collection' => $this->collection,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn (): ?array => $this->creator?->only(['id', 'username', 'name'])),
            'download_url' => self::downloadUrl($this->attachment(), $expiresAt),
            'preview_url' => self::previewUrl($this->attachment(), $expiresAt),
            'url_expires_at' => $expiresAt->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * 输出的附件。
     */
    private function attachment(): Attachment
    {
        return $this->resource instanceof Attachment ? $this->resource : throw new LogicException('AttachmentResource 只输出 Attachment。');
    }

    /**
     * 签发的地址的过期时间，对齐到固定的时间窗口：`expires = (floor(now / ttl) + 2) * ttl`（秒）。
     * 同一个窗口内签出的地址完全相同（浏览器可以缓存），有效期在 ttl 与 2 × ttl 之间（至少一个 ttl）。
     *
     * @param  ?int  $ttlMinutes  窗口长度（分钟），默认 config('core.attachments.url_ttl')；头像用 avatar_url_ttl
     */
    public static function urlExpiresAt(?int $ttlMinutes = null): CarbonInterface
    {
        $ttl = max(1, $ttlMinutes ?? config()->integer('core.attachments.url_ttl')) * 60;

        return now()->setTimestamp((intdiv(now()->getTimestamp(), $ttl) + 2) * $ttl);
    }

    /**
     * 头像地址的过期时间（窗口为 config('core.attachments.avatar_url_ttl') 分钟）。
     */
    public static function avatarUrlExpiresAt(): CarbonInterface
    {
        return self::urlExpiresAt(config()->integer('core.attachments.avatar_url_ttl'));
    }

    /**
     * 签名的下载地址（Content-Disposition: attachment）。
     */
    public static function downloadUrl(Attachment $attachment, ?CarbonInterface $expiresAt = null): string
    {
        return URL::temporarySignedRoute(AdminServiceProvider::NAME.'.attachments.download', $expiresAt ?? self::urlExpiresAt(), ['attachment' => $attachment->id]);
    }

    /**
     * 签名的预览地址（只限图片白名单，inline）；不可预览时为 null。头像（/user/info 的 avatar）也用它。
     */
    public static function previewUrl(Attachment $attachment, ?CarbonInterface $expiresAt = null): ?string
    {
        return $attachment->isPreviewable()
            ? URL::temporarySignedRoute(AdminServiceProvider::NAME.'.attachments.preview', $expiresAt ?? self::urlExpiresAt(), ['attachment' => $attachment->id])
            : null;
    }
}
