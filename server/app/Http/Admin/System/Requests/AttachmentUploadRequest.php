<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Rules\AllowedUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 上传附件（POST /attachments，multipart/form-data，设计文档 §6.8）：只有 `file` 一个字段。
 *
 * 类型白名单与大小上限由 System 模块的 AllowedUpload 按系统参数校验（内容探测的 MIME 与客户端扩展名都在白名单中，
 * 不信任客户端的 Content-Type）。未声明的字段一律拒绝（failOnUnknownFields）；授权写在控制器的 #[Authorize] 上。
 */
class AttachmentUploadRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', new AllowedUpload],
        ];
    }
}
