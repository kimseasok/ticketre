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
        $title = $this->faker->sentence();
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
            'title' => $title,
            'slug' => Str::slug($title.'-'.$category->brand_id),
            'content' => $this->faker->paragraphs(3, true),
            'locale' => 'en',
            'status' => 'published',
            'metadata' => ['keywords' => $this->faker->words(4)],
            'published_at' => now(),
            'excerpt' => $this->faker->sentences(2, true),
        ];
    }
}
