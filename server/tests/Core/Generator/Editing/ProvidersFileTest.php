<?php

use App\Core\Generator\Editing\{ProvidersFile, UnexpectedFormatException};
use App\Http\Admin\AdminServiceProvider;
use App\Providers\AppServiceProvider;

/*
|--------------------------------------------------------------------------
| bootstrap/providers.php：按类别分组登记，幂等，格式不符时给出需手工粘贴的内容
|--------------------------------------------------------------------------
*/

it('appends a new group at the end when no later group exists', function (): void {
    $code = "<?php\n\nuse App\\Providers\\AppServiceProvider;\n\nreturn [\n    AppServiceProvider::class,\n];\n";

    expect(ProvidersFile::register($code, AdminServiceProvider::class))->toBe(<<<'PHP'
        <?php

        use App\Http\Admin\AdminServiceProvider;
        use App\Providers\AppServiceProvider;

        return [
            AppServiceProvider::class,

            // 入口：是否加载由 config('core.apps') / CORE_APPS 决定
            AdminServiceProvider::class,
        ];

        PHP);
});

it('keeps third-party providers last', function (): void {
    $code = "<?php\n\nreturn [\n    Vendor\\Package\\PackageServiceProvider::class,\n];\n";

    expect(ProvidersFile::register($code, 'App\Modules\Billing\BillingServiceProvider'))
        ->toContain("return [\n    // 业务模块（app/Modules）\n    BillingServiceProvider::class,\n\n    Vendor\\Package\\PackageServiceProvider::class,\n];");
});

it('returns null when the provider is already registered', function (): void {
    $code = "<?php\n\nreturn [\n    App\\Providers\\AppServiceProvider::class,\n];\n";

    expect(ProvidersFile::register($code, AppServiceProvider::class))->toBeNull();
});

it('rejects lines it does not understand', function (string $code): void {
    expect(fn (): ?string => ProvidersFile::register($code, 'App\Modules\Billing\BillingServiceProvider'))
        ->toThrow(function (UnexpectedFormatException $e): void {
            expect($e->snippet)->toBe("use App\\Modules\\Billing\\BillingServiceProvider;\n\n    BillingServiceProvider::class,");
        });
})->with([
    'not an array literal' => "<?php\n\nreturn array_merge([], []);\n",
    'conditional entry' => "<?php\n\nreturn [\n    ...(true ? [] : []),\n];\n",
]);
