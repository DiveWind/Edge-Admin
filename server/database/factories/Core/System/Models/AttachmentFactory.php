<?php

namespace Database\Factories\Core\System\Models;

use App\Core\System\Models\{Attachment, User};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 附件：默认是未绑定的 PNG 记录（不写文件；需要文件时用 Storage::fake() 后 withFile()，或走上传接口）。
 *
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk' => config()->string('core.system.attachment.disk'),
            'path' => Attachment::DIRECTORY.'/'.now()->format('Y/m/d').'/'.Str::random(40).'.png',
            'name' => fake()->word().'.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size' => fake()->numberBetween(100, 100_000),
            'sha256' => hash('sha256', Str::random()),
            'created_by' => User::factory(),
        ];
    }

    /**
     * 绑定在 $record 的 $collection 用途上。
     */
    public function boundTo(Model $record, string $collection = 'default'): static
    {
        return $this->state(fn (): array => [
            'attachable_type' => $record->getMorphClass(),
            'attachable_id' => $record->getKey(),
            'collection' => $collection,
        ]);
    }

    /**
     * 指定 MIME 与扩展名的附件。
     */
    public function ofType(string $mimeType, string $extension): static
    {
        return $this->state(fn (): array => [
            'mime_type' => $mimeType,
            'extension' => $extension,
            'name' => fake()->word().'.'.$extension,
            'path' => Attachment::DIRECTORY.'/'.now()->format('Y/m/d').'/'.Str::random(40).'.'.$extension,
        ]);
    }

    /**
     * 在存储盘上写入对应的文件（内容为 $contents）。
     */
    public function withFile(string $contents = 'content'): static
    {
        return $this->afterCreating(function (Attachment $attachment) use ($contents): void {
            Storage::disk($attachment->disk)->put($attachment->path, $contents);
        });
    }
}
