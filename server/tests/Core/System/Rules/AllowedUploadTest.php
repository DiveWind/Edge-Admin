<?php

use App\Core\System\Actions\UpdateSettings;
use App\Core\System\Rules\AllowedUpload;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| AllowedUpload：上传的类型白名单与大小上限取自系统参数（设计文档 §6.8）
|--------------------------------------------------------------------------
|
| 按内容伪造、按扩展名伪造与大小上限的接口行为见 tests/Http/Admin/System/Controllers/AttachmentControllerTest.php。
|
*/

/**
 * 用 AllowedUpload 校验一个 PNG 文件（名为 $name），返回是否通过。
 */
function passesAllowedUpload(string $name): bool
{
    return Validator::make(['file' => uploadedFile($name, pngBytes())], ['file' => [new AllowedUpload]])->passes();
}

it('normalizes the whitelist: spaces, upper case and duplicates', function (): void {
    expect(AllowedUpload::allowedExtensions(' PNG, jpg ,png,, '))->toBe(['png', 'jpg']);

    resolve(UpdateSettings::class)->handle(['attachment.allowed_extensions' => 'png']);

    expect(passesAllowedUpload('photo.PNG'))->toBeTrue()
        ->and(passesAllowedUpload('photo.jpg'))->toBeFalse();
});

it('rejects every file when the whitelist is empty', function (): void {
    resolve(UpdateSettings::class)->handle(['attachment.allowed_extensions' => ' , ']);

    expect(passesAllowedUpload('photo.png'))->toBeFalse();
});
