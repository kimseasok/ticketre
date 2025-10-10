<?php

namespace App\Http\Requests;

use App\Models\Brand;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();
        /** @var Brand $brand */
        $brand = $this->route('brand');

        return $user?->can('brands.manage') && $user->tenant_id === $brand->tenant_id;
    }

    public function rules(): array
    {
        /** @var Brand $brand */
        $brand = $this->route('brand');
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('brands', 'slug')
                    ->ignore($brand->getKey())
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^(?!-)(?:[a-zA-Z0-9-]{1,63}\.)+[A-Za-z]{2,}$/',
                Rule::unique('brands', 'domain')
                    ->ignore($brand->getKey())
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'theme' => ['nullable', 'array'],
            'theme.primary' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{3,6}$/'],
            'theme.secondary' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{3,6}$/'],
            'theme.accent' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{3,6}$/'],
            'theme.text' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{3,6}$/'],
            'theme_settings' => ['nullable', 'array'],
            'theme_settings.button_radius' => ['nullable', 'integer', 'min:0', 'max:24'],
            'theme_settings.font_family' => ['nullable', 'string', 'max:64'],
            'primary_logo_path' => ['nullable', 'string', 'max:2048'],
            'secondary_logo_path' => ['nullable', 'string', 'max:2048'],
            'favicon_path' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
