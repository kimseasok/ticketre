<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreRedisConfigurationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = $this->user();

        return $user?->can('infrastructure.redis.manage') ?? false;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();
        $brandId = $this->input('brand_id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('redis_configurations', 'slug')
                    ->where(function ($query) use ($tenantId, $brandId) {
                        $query->where('tenant_id', $tenantId)->whereNull('deleted_at');

                        if ($brandId === null) {
                            $query->whereNull('brand_id');
                        } else {
                            $query->where('brand_id', $brandId);
                        }

                        return $query;
                    }),
            ],
            'brand_id' => [
                'nullable',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'cache_connection_name' => ['nullable', 'string', 'max:64'],
            'cache_host' => ['required', 'string', 'max:255'],
            'cache_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'cache_database' => ['required', 'integer', 'min:0', 'max:16'],
            'cache_tls' => ['sometimes', 'boolean'],
            'cache_prefix' => ['nullable', 'string', 'max:255'],
            'session_connection_name' => ['nullable', 'string', 'max:64'],
            'session_host' => ['required', 'string', 'max:255'],
            'session_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'session_database' => ['required', 'integer', 'min:0', 'max:16'],
            'session_tls' => ['sometimes', 'boolean'],
            'session_lifetime_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'use_for_cache' => ['sometimes', 'boolean'],
            'use_for_sessions' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'fallback_store' => ['nullable', Rule::in(['file', 'array'])],
            'cache_auth_secret' => ['nullable', 'string', 'max:512'],
            'session_auth_secret' => ['nullable', 'string', 'max:512'],
            'options' => ['nullable', 'array'],
        ];
    }

    protected function tenantId(): ?int
    {
        if ($this->user()) {
            return $this->user()->tenant_id;
        }

        if (app()->bound('currentTenant') && app('currentTenant')) {
            return app('currentTenant')->getKey();
        }

        return null;
    }
}
