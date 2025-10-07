<?php

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Support\Str;

it('creates knowledge base article', function () {
    $category = KbCategory::factory()->create();
    $author = User::factory()->create([
        'tenant_id' => $category->tenant_id,
        'brand_id' => $category->brand_id,
    ]);

    $article = KbArticle::query()->create([
        'tenant_id' => $category->tenant_id,
        'brand_id' => $category->brand_id,
        'category_id' => $category->getKey(),
        'author_id' => $author->getKey(),
        'slug' => Str::slug('Demo Article '.$category->brand_id),
        'default_locale' => 'en',
    ]);

    $translation = $article->translations()->create([
        'tenant_id' => $article->tenant_id,
        'brand_id' => $article->brand_id,
        'locale' => $article->default_locale,
        'title' => 'Demo Article',
        'content' => 'Knowledge base content',
        'status' => 'draft',
    ]);

    expect($translation->fresh()->title)->toBe('Demo Article');
    expect($article->fresh()->translationForLocale()->title)->toBe('Demo Article');
});

it('updates knowledge base article status', function () {
    $category = KbCategory::factory()->create();
    $author = User::factory()->create([
        'tenant_id' => $category->tenant_id,
        'brand_id' => $category->brand_id,
    ]);

    $article = KbArticle::query()->create([
        'tenant_id' => $category->tenant_id,
        'brand_id' => $category->brand_id,
        'category_id' => $category->getKey(),
        'author_id' => $author->getKey(),
        'slug' => Str::slug('Draft Article '.$category->brand_id),
        'default_locale' => 'en',
    ]);

    $translation = $article->translations()->create([
        'tenant_id' => $article->tenant_id,
        'brand_id' => $article->brand_id,
        'locale' => $article->default_locale,
        'title' => 'Draft Article',
        'content' => 'Initial body',
        'status' => 'draft',
    ]);

    $translation->update(['status' => 'published']);

    expect($translation->fresh()->status)->toBe('published');
    expect($article->fresh()->translationForLocale()->status)->toBe('published');
});
