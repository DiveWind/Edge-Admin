<?php

namespace App\Http\Admin\System\Resources;

use App\Core\System\Models\{Attachment, Export};
use App\Core\System\Services\ExporterRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 导出记录的输出格式（Admin 入口，设计文档 §6.10）。
 *
 * - name：导出类的对外名称；title：导出的显示名（后端文案，按当前请求的语言翻译；导出类已不再登记时为 name）；
 * - params：列表参数 `{ filter, sort }`（为空时为 `[]`）；row_count：完成时写出的行数；
 * - file：生成的文件（附件，格式同 AttachmentResource，签名的下载地址由本接口签发）；完成前、未加载 file 关联时为 null；
 * - expires_at：到期清理的时间（创建时间 + config('core.system.export.ttl_hours') 小时）；时间为带时区偏移的 ISO 8601。
 *
 * @mixin Export
 */
class ExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $file = $this->relationLoaded('file') ? $this->file : null;

        return [
            'id' => $this->id,
            'name' => $this->exporter,
            'title' => $this->title(),
            'format' => $this->format,
            'status' => $this->status,
            'params' => $this->parameters(),
            'row_count' => $this->row_count,
            'file' => $file instanceof Attachment ? new AttachmentResource($file) : null,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'expires_at' => $this->expiresAt()?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * 导出的显示名（按当前语言）。
     */
    private function title(): string
    {
        $exporters = resolve(ExporterRegistry::class);

        if (! $exporters->has($this->exporter)) {
            return $this->exporter;
        }

        $title = $exporters->get($this->exporter)->title();
        $translated = __($title);

        return is_string($translated) ? $translated : $title;
    }
}
