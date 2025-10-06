<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreKbArticleRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->brandId();

        return [
            'brand_id' => [
                'required',
                'integer',
                Rule::exists('brands', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('kb_categories', 'id')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('brand_id', $brandId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('kb_articles', 'slug')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('brand_id', $brandId)
                    ->where('locale', $this->input('locale', 'en')),
            ],
            'locale' => ['required', 'string', 'max:10'],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
            'published_at' => ['nullable', 'date'],
            'author_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }

    protected function brandId(): ?int
    {
        return $this->input('brand_id') ?? (app()->bound('currentBrand') && app('currentBrand') ? (int) app('currentBrand')->getKey() : null);
    }
}
