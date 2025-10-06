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
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('kb_articles', 'slug')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('brand_id', $brandId),
            ],
            'default_locale' => ['nullable', 'string', 'max:10'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.locale' => ['required', 'string', 'max:10'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'translations.*.content' => ['required', 'string'],
            'translations.*.excerpt' => ['nullable', 'string'],
            'translations.*.metadata' => ['nullable', 'array'],
            'translations.*.metadata.*' => ['nullable'],
            'translations.*.published_at' => ['nullable', 'date'],
            'author_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('default_locale') && is_array($this->input('translations'))) {
            $firstLocale = collect($this->input('translations'))
                ->pluck('locale')
                ->filter()
                ->first();

            if ($firstLocale) {
                $this->merge(['default_locale' => $firstLocale]);
            }
        }
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $locales = collect($this->input('translations', []))
                ->pluck('locale');

            if ($locales->duplicates()->isNotEmpty()) {
                $validator->errors()->add('translations', 'Locales must be unique per article.');
            }

            if ($locales->isNotEmpty() && ! $locales->contains($this->input('default_locale'))) {
                $validator->errors()->add('default_locale', 'Default locale must match one of the provided translations.');
            }
        });
    }

    protected function brandId(): ?int
    {
        return $this->input('brand_id') ?? (app()->bound('currentBrand') && app('currentBrand') ? (int) app('currentBrand')->getKey() : null);
    }
}
