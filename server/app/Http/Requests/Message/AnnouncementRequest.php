<?php

namespace App\Http\Requests\Message;

use App\Enums\AnnouncementType;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    /**
     * 编辑时的记录级授权（Policy），先于字段校验执行。
     */
    public function authorize(): Response
    {
        $announcement = $this->route('announcement');

        return $announcement === null ? Response::allow() : Gate::inspect('update', $announcement);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', Rule::enum(AnnouncementType::class)],
        ];
    }
}
