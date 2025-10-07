<?php

use App\Models\KbArticle;

it('creates knowledge base article', function () {
    $article = KbArticle::factory()->create();
    $article->defaultTranslation->update(['title' => 'Demo Article']);

    expect($article->fresh()->defaultTranslation->title)->toBe('Demo Article');
});

it('updates knowledge base article status', function () {
    $article = KbArticle::factory()->create();
    $article->defaultTranslation->update(['status' => 'draft']);

    $article->defaultTranslation->update(['status' => 'published']);

    expect($article->fresh()->defaultTranslation->status)->toBe('published');
});
