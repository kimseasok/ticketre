<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateKbCategoryRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('kb_category');
        $brandId = $this->brandId($category?->brand_id);

        $parentRules = [
            'nullable',
            'integer',
            Rule::exists('kb_categories', 'id')
                ->where('tenant_id', $this->user()->tenant_id)
                ->where('brand_id', $brandId),
        ];

        if ($category) {
            $parentRules[] = Rule::notIn([$category->getKey()]);
        }

        return [
            'brand_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('brands', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('kb_categories', 'slug')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('brand_id', $brandId)
                    ->ignore($category?->getKey()),
            ],
            'parent_id' => $parentRules,
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function brandId(?int $fallback): ?int
    {
        return $this->input('brand_id') ?? $fallback ?? (app()->bound('currentBrand') && app('currentBrand') ? (int) app('currentBrand')->getKey() : null);
    }
}
