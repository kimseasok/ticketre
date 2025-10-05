<?php

namespace Database\Factories;

use App\Models\KbArticle;
use App\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KbArticleFactory extends Factory
{
    protected $model = KbArticle::class;

    public function definition(): array
    {
        $categoryId = $this->attributes['category_id'] ?? KbCategory::factory()->create()->id;
        $category = KbCategory::find($categoryId);
        $title = $this->faker->sentence();

        return [
            'tenant_id' => $category->tenant_id,
            'category_id' => $categoryId,
            'title' => $title,
            'slug' => Str::slug($title.'-'.$category->tenant_id),
            'content' => $this->faker->paragraphs(3, true),
            'locale' => 'en',
            'status' => 'published',
            'metadata' => [],
            'published_at' => now(),
        ];
    }
}
