<?php

namespace App\Http\Controllers\Api\Options;

use App\Http\Controllers\Controller;
use App\Http\Resources\OptionResource;
use App\Models\ArticleCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 启用文章分类（下拉选项，登录即可访问）。
 */
class ArticleCategoryOptionController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return OptionResource::collection(ArticleCategory::query()->active()->orderBy('sort')->orderBy('id')->get(['id', 'name']));
    }
}
