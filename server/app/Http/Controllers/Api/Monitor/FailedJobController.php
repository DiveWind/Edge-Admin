<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Resources\FailedJobResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;

class FailedJobController extends Controller
{
    public function __construct(protected FailedJobProviderInterface $failer) {}

    public function index(): AnonymousResourceCollection
    {
        return FailedJobResource::collection($this->failer->all());
    }

    /**
     * 重新入队（官方 queue:retry 命令）。
     */
    public function retry(string $uuid): Response
    {
        $this->failer->find($uuid) ?? abort(404);

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return response()->noContent();
    }

    public function destroy(string $uuid): Response
    {
        $this->failer->find($uuid) ?? abort(404);

        $this->failer->forget($uuid);

        return response()->noContent();
    }
}
