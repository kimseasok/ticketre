<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBrandAssetRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        return $user?->can('brand_assets.manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'brand_id' => [
                'sometimes',
                'integer',
                Rule::exists('brands', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::in(config('branding.asset_types', [])),
            ],
            'disk' => ['sometimes', 'string', 'max:64'],
            'path' => ['sometimes', 'string', 'max:2048'],
            'content_type' => ['sometimes', 'string', 'max:128'],
            'size' => ['sometimes', 'integer', 'min:0'],
            'checksum' => ['sometimes', 'string', 'max:128'],
            'meta' => ['sometimes', 'array'],
            'cache_control' => ['sometimes', 'string', 'max:128'],
            'cdn_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
