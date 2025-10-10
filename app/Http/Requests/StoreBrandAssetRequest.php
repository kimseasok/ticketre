<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreBrandAssetRequest extends ApiFormRequest
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
                'required',
                'integer',
                Rule::exists('brands', 'id')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'type' => [
                'required',
                'string',
                Rule::in(config('branding.asset_types', [])),
            ],
            'disk' => ['nullable', 'string', 'max:64'],
            'path' => ['required', 'string', 'max:2048'],
            'content_type' => ['nullable', 'string', 'max:128'],
            'size' => ['nullable', 'integer', 'min:0'],
            'checksum' => ['nullable', 'string', 'max:128'],
            'meta' => ['nullable', 'array'],
            'cache_control' => ['nullable', 'string', 'max:128'],
            'cdn_url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
