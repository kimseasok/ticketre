<?php

namespace Database\Factories;

use App\Models\KbArticle;
use App\Models\KbArticleTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

class KbArticleTranslationFactory extends Factory
{
    protected $model = KbArticleTranslation::class;

    public function definition(): array
    {
        $article = $this->attributes['kb_article_id']
            ? KbArticle::query()->findOrFail($this->attributes['kb_article_id'])
            : KbArticle::factory()->create();

        return [
            'kb_article_id' => $article->getKey(),
            'tenant_id' => $article->tenant_id,
            'brand_id' => $article->brand_id,
            'locale' => $this->faker->unique()->languageCode(),
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'status' => 'draft',
            'excerpt' => $this->faker->sentences(2, true),
            'metadata' => ['keywords' => $this->faker->words(3)],
        ];
    }
}
