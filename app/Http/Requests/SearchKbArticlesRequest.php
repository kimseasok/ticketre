<?php

namespace App\Http\Requests;

use App\Models\KbCategory;
use Illuminate\Validation\Rule;

class SearchKbArticlesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('knowledge.view') || $this->user()?->can('knowledge.manage');
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $brandId = app()->bound('currentBrand') && app('currentBrand')
            ? app('currentBrand')->getKey()
            : $this->user()?->brand_id;

        return [
            'q' => ['required', 'string', 'min:2', 'max:255'],
            'locale' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists((new KbCategory())->getTable(), 'id')
                    ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
                    ->when($brandId, fn ($query) => $query->where('brand_id', $brandId)),
            ],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
