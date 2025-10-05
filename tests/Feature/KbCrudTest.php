<?php

use App\Models\KbArticle;

it('creates knowledge base article', function () {
    $article = KbArticle::factory()->create(['title' => 'Demo Article']);

    expect($article->fresh()->title)->toBe('Demo Article');
});

it('updates knowledge base article status', function () {
    $article = KbArticle::factory()->create(['status' => 'draft']);
    $article->update(['status' => 'published']);

    expect($article->fresh()->status)->toBe('published');
});
