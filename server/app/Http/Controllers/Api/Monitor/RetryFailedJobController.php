<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Actions\Monitor\RetryFailedJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

class RetryFailedJobController extends Controller
{
    /**
     * 重新入队（官方 queue:retry 命令）。
     */
    public function __invoke(string $uuid, FailedJobProviderInterface $failer, RetryFailedJob $retryFailedJob): Response
    {
        $failer->find($uuid) ?? abort(404);

        $retryFailedJob->handle($uuid);

        return response()->noContent();
    }
}
