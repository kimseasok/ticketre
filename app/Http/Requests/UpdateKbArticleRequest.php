<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateKbArticleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $article = $this->route('kb_article');
        $brandId = $this->brandId($article?->brand_id);
        $locale = $this->input('locale', $article?->locale ?? 'en');

        return [
            'brand_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('brands', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('kb_categories', 'id')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('brand_id', $brandId),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('kb_articles', 'slug')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('brand_id', $brandId)
                    ->where('locale', $locale)
                    ->ignore($article?->getKey()),
            ],
            'locale' => ['sometimes', 'required', 'string', 'max:10'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'content' => ['sometimes', 'required', 'string'],
            'excerpt' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
            'published_at' => ['nullable', 'date'],
            'author_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }

    protected function brandId(?int $fallback): ?int
    {
        return $this->input('brand_id') ?? $fallback ?? (app()->bound('currentBrand') && app('currentBrand') ? (int) app('currentBrand')->getKey() : null);
    }
}
