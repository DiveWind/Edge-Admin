<?php

use App\Models\{Article, ArticleCategory, Department};

test('creating an article fills author and department automatically', function () {
    $department = Department::factory()->create();
    $actor = actingAsUser(userWithPermissions(['content.article.create'], attributes: ['department_id' => $department->id]));
    $category = ArticleCategory::factory()->create();

    $response = $this->postJson('/api/articles', [
        'category_id' => $category->id,
        'title' => '第一篇文章',
        'content' => '正文',
        'status' => 'published',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.author.id', $actor->id)
        ->assertJsonPath('data.department.id', $department->id)
        ->assertJsonPath('data.category', ['id' => $category->id, 'name' => $category->name]);

    expect($response->json('data.published_at'))->not->toBeNull();
});

test('published_at is set when an article becomes published', function () {
    actingAsUser(superAdmin());
    $article = Article::factory()->create(['status' => 'draft', 'published_at' => null]);

    $this->putJson("/api/articles/{$article->id}", [
        'category_id' => $article->category_id,
        'title' => $article->title,
        'content' => $article->content,
        'status' => 'published',
    ])->assertOk();

    expect($article->fresh()->published_at)->not->toBeNull();
});

test('articles can be filtered', function () {
    actingAsUser(superAdmin());
    $category = ArticleCategory::factory()->create();
    Article::factory()->create(['title' => 'Laravel 指南', 'category_id' => $category->id]);
    Article::factory()->create(['title' => '其他']);

    $this->getJson('/api/articles?title=laravel')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/articles?category_id={$category->id}")->assertOk()->assertJsonCount(1, 'data');
});

test('article list is paginated with the standard envelope', function () {
    actingAsUser(superAdmin());
    Article::factory(3)->create();

    $this->getJson('/api/articles?per_page=2&sort=-created_at')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']])
        ->assertJsonPath('meta.total', 3);

    $this->getJson('/api/articles?per_page=1000')->assertUnprocessable();
    $this->getJson('/api/articles?sort=content')->assertUnprocessable();
});

test('categories with articles cannot be deleted', function () {
    actingAsUser(superAdmin());
    $category = ArticleCategory::factory()->create();
    Article::factory()->create(['category_id' => $category->id]);

    $this->deleteJson("/api/article-categories/{$category->id}")->assertForbidden();
    $this->deleteJson('/api/article-categories/'.ArticleCategory::factory()->create()->id)->assertNoContent();
});
