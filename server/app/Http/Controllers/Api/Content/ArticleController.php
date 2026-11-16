<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ArticleIndexRequest;
use App\Http\Requests\Content\ArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * 业务模块范本：列表用 visibleTo() 过滤，单条读写用 Policy 判定数据权限。
 */
class ArticleController extends Controller
{
    /**
     * @var list<string>
     */
    protected array $relations = ['category', 'author', 'department'];

    public function index(ArticleIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $query = Article::query()
            ->with($this->relations)
            ->visibleTo($actor)
            ->filter($request->filters());

        return ArticleResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(ArticleRequest $request): ArticleResource
    {
        $article = Article::query()->create($request->validated());

        return new ArticleResource($article->load($this->relations));
    }

    public function show(Article $article): ArticleResource
    {
        Gate::authorize('view', $article);

        return new ArticleResource($article->load($this->relations));
    }

    public function update(ArticleRequest $request, Article $article): ArticleResource
    {
        $article->update($request->validated());

        return new ArticleResource($article->load($this->relations));
    }

    public function destroy(Article $article): Response
    {
        Gate::authorize('delete', $article);

        $article->delete();

        return response()->noContent();
    }
}
