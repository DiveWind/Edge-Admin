<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadRequest extends FormRequest
{
    /**
     * 允许的扩展名与大小上限见 config('edge.uploads')，按文件真实 MIME 校验。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', File::types(config()->array('edge.uploads.extensions'))->max(config()->integer('edge.uploads.max_size'))],
        ];
    }
}
