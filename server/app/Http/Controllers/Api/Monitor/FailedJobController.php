<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Resources\FailedJobResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class FailedJobController extends Controller
{
    public function __construct(protected FailedJobProviderInterface $failer) {}

    #[Authorize('monitor.failed-job.view')]
    public function index(): AnonymousResourceCollection
    {
        return FailedJobResource::collection($this->failer->all());
    }

    #[Authorize('monitor.failed-job.delete')]
    public function destroy(string $uuid): Response
    {
        $this->failer->find($uuid) ?? abort(404);

        $this->failer->forget($uuid);

        return response()->noContent();
    }
}
