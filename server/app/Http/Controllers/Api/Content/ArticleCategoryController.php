<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\{ArticleCategoryIndexRequest, ArticleCategoryRequest};
use App\Http\Resources\ArticleCategoryResource;
use App\Models\ArticleCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class ArticleCategoryController extends Controller
{
    #[Authorize('content.category.view')]
    public function index(ArticleCategoryIndexRequest $request): AnonymousResourceCollection
    {
        $query = ArticleCategory::query()->withCount('articles')->filter($request->filters());

        return ArticleCategoryResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    #[Authorize('content.category.create')]
    public function store(ArticleCategoryRequest $request): ArticleCategoryResource
    {
        return new ArticleCategoryResource(ArticleCategory::query()->create($request->validated()));
    }

    #[Authorize('content.category.view')]
    public function show(ArticleCategory $articleCategory): ArticleCategoryResource
    {
        return new ArticleCategoryResource($articleCategory->loadCount('articles'));
    }

    #[Authorize('content.category.update')]
    public function update(ArticleCategoryRequest $request, ArticleCategory $articleCategory): ArticleCategoryResource
    {
        $articleCategory->update($request->validated());

        return new ArticleCategoryResource($articleCategory);
    }

    #[Authorize('content.category.delete')]
    #[Authorize('delete', 'article_category')]
    public function destroy(ArticleCategory $articleCategory): Response
    {
        $articleCategory->delete();

        return response()->noContent();
    }
}
