<?php

namespace App\Http\Controllers\Api\Options;

use App\Http\Controllers\Controller;
use App\Http\Resources\OptionResource;
use App\Models\Position;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 启用岗位（下拉选项，登录即可访问）。
 */
class PositionOptionController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return OptionResource::collection(Position::query()->active()->orderBy('sort')->orderBy('id')->get(['id', 'name', 'code']));
    }
}
