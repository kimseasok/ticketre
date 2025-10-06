<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;
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
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('kb_articles', 'slug')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('brand_id', $brandId)
                    ->ignore($article?->getKey()),
            ],
            'default_locale' => ['sometimes', 'required', 'string', 'max:10'],
            'translations' => ['sometimes', 'array'],
            'translations.*.id' => [
                'sometimes',
                'integer',
                Rule::exists('kb_article_translations', 'id')->where('kb_article_id', $article?->getKey() ?? 0),
            ],
            'translations.*.locale' => ['required_with:translations', 'string', 'max:10'],
            'translations.*.delete' => ['sometimes', 'boolean'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.status' => ['nullable', 'string', Rule::in(['draft', 'published', 'archived'])],
            'translations.*.content' => ['nullable', 'string'],
            'translations.*.excerpt' => ['nullable', 'string'],
            'translations.*.metadata' => ['nullable', 'array'],
            'translations.*.metadata.*' => ['nullable'],
            'translations.*.published_at' => ['nullable', 'date'],
            'author_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('translations')) {
            return;
        }

        $translations = collect($this->input('translations'))
            ->map(function ($translation) {
                if (! is_array($translation)) {
                    return $translation;
                }

                if (array_key_exists('delete', $translation)) {
                    $translation['delete'] = filter_var($translation['delete'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
                }

                return $translation;
            })
            ->all();

        $this->merge(['translations' => $translations]);
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $translations = collect($this->input('translations', []));

            if ($translations->isEmpty()) {
                return;
            }

            $activeLocales = $translations
                ->reject(fn ($translation) => Arr::get($translation, 'delete'))
                ->pluck('locale');

            if ($activeLocales->duplicates()->isNotEmpty()) {
                $validator->errors()->add('translations', 'Locales must be unique per article.');
            }

            if ($activeLocales->isEmpty() && ($this->route('kb_article')?->translations()->count() ?? 0) === 0) {
                $validator->errors()->add('translations', 'At least one translation is required.');
            }

            $translations->each(function ($translation, $index) use ($validator) {
                if (Arr::get($translation, 'delete')) {
                    return;
                }

                foreach (['title', 'status', 'content'] as $field) {
                    if (! Arr::has($translation, $field) || Arr::get($translation, $field) === null || Arr::get($translation, $field) === '') {
                        $validator->errors()->add("translations.$index.$field", ucfirst($field).' is required when translation is not deleted.');
                    }
                }
            });

            if ($activeLocales->isNotEmpty() && $this->filled('default_locale') && ! $activeLocales->contains($this->input('default_locale'))) {
                $validator->errors()->add('default_locale', 'Default locale must match one of the active translations.');
            }
        });
    }

    protected function brandId(?int $fallback): ?int
    {
        return $this->input('brand_id') ?? $fallback ?? (app()->bound('currentBrand') && app('currentBrand') ? (int) app('currentBrand')->getKey() : null);
    }
}
