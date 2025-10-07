<?php

use App\Models\KbArticle;

it('creates knowledge base article with a default translation', function () {
    $article = KbArticle::factory()->create();

    $translation = $article->fresh()->defaultTranslation;

    expect($translation)->not->toBeNull()
        ->and($translation?->title)->not->toBeEmpty();
});

it('updates knowledge base article translation status', function () {
    $article = KbArticle::factory()->create();

    $translation = $article->defaultTranslation;
    $translation->update(['status' => 'archived']);

    expect($article->fresh()->defaultTranslation?->status)->toBe('archived');
});

it('scopes published knowledge base articles via translations', function () {
    $published = KbArticle::factory()->create();
    $draft = KbArticle::factory()->create();

    $draft->defaultTranslation->update(['status' => 'draft']);

    $results = KbArticle::query()->published()->get();

    expect($results->pluck('id'))->toContain($published->id)
        ->and($results->pluck('id'))->not->toContain($draft->id);
});
