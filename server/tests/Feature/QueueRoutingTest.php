<?php

use Symfony\Component\Finder\Finder;

test('every job is registered in the central queue routing table', function () {
    $jobs = collect(Finder::create()->files()->name('*.php')->in(app_path('Jobs')))
        ->map(fn (SplFileInfo $file): string => 'App\\Jobs\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
        ->values();

    expect($jobs)->not->toBeEmpty()
        ->and($jobs->diff(array_keys(app('queue.routes')->all()))->values()->all())
        ->toBe([], '请在 AppServiceProvider::configureQueues() 的 Queue::route() 中登记这些 Job');
});
