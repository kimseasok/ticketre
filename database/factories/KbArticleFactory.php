<?php

namespace Database\Factories;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KbArticleFactory extends Factory
{
    protected $model = KbArticle::class;

    public function definition(): array
    {
        $categoryId = $this->attributes['category_id'] ?? KbCategory::factory()->create()->id;
        $category = KbCategory::query()->withTrashed()->findOrFail($categoryId);
        $author = isset($this->attributes['author_id'])
            ? User::query()->findOrFail($this->attributes['author_id'])
            : User::factory()->create([
                'tenant_id' => $category->tenant_id,
                'brand_id' => $category->brand_id,
            ]);

        return [
            'tenant_id' => $category->tenant_id,
            'brand_id' => $category->brand_id,
            'category_id' => $categoryId,
            'author_id' => $author->getKey(),
            'slug' => Str::slug($this->faker->unique()->sentence().' '.$category->brand_id),
            'default_locale' => 'en',
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (KbArticle $article) {
            $title = $this->faker->sentence();

            $article->translations()->create([
                'tenant_id' => $article->tenant_id,
                'brand_id' => $article->brand_id,
                'locale' => $article->default_locale,
                'title' => $title,
                'content' => $this->faker->paragraphs(3, true),
                'status' => 'published',
                'excerpt' => $this->faker->sentences(2, true),
                'metadata' => ['keywords' => $this->faker->words(4)],
                'published_at' => now(),
            ]);
        });
    }
}
