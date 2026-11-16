<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ArticleCategoryIndexRequest;
use App\Http\Requests\Content\ArticleCategoryRequest;
use App\Http\Resources\ArticleCategoryResource;
use App\Models\ArticleCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ArticleCategoryController extends Controller
{
    public function index(ArticleCategoryIndexRequest $request): AnonymousResourceCollection
    {
        $query = ArticleCategory::query()->withCount('articles')->filter($request->filters());

        return ArticleCategoryResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(ArticleCategoryRequest $request): ArticleCategoryResource
    {
        return new ArticleCategoryResource(ArticleCategory::query()->create($request->validated()));
    }

    public function show(ArticleCategory $articleCategory): ArticleCategoryResource
    {
        return new ArticleCategoryResource($articleCategory->loadCount('articles'));
    }

    public function update(ArticleCategoryRequest $request, ArticleCategory $articleCategory): ArticleCategoryResource
    {
        $articleCategory->update($request->validated());

        return new ArticleCategoryResource($articleCategory);
    }

    public function destroy(ArticleCategory $articleCategory): Response
    {
        Gate::authorize('delete', $articleCategory);

        $articleCategory->delete();

        return response()->noContent();
    }
}
