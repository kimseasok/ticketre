<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreKbCategoryRequest extends ApiFormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('kb_categories', 'slug')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('brand_id', $brandId),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('kb_categories', 'id')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('brand_id', $brandId),
            ],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function brandId(): ?int
    {
        return $this->input('brand_id') ?? (app()->bound('currentBrand') && app('currentBrand') ? (int) app('currentBrand')->getKey() : null);
    }
}
